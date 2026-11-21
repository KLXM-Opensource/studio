<?php
declare(strict_types=1);

namespace Klxm\Psalm;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Psalm\Plugin\EventHandler\AddTaintsInterface;
use Psalm\Plugin\EventHandler\Event\AddRemoveTaintsEvent;
use Psalm\Plugin\EventHandler\RemoveTaintsInterface;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Psalm\Type\TaintKind;
use Psalm\Type\TaintKindGroup;
use SimpleXMLElement;

/**
 * Quellen und Escape-Stellen von KLXM Studio für die Taint-Analyse (nur Entwicklung).
 *
 * Psalm übernimmt Taint-Kommentare aus Stub-Dateien nur für Funktionen und fremde Klassen (vendor/), nicht für Klassen
 * des Projekts – deshalb stehen die Regeln für Core\Http\Request und Core\Sanitizer hier statt im Code:
 *  - Quelle: Core\Http\Request::input()/str()/host() und die Eigenschaften query/post/files/server
 *  - Escape: Core\Sanitizer::inline()/block() (Whitelist-HTML, Links geprüft)
 *  - Escape html: json_encode(…, JSON_HEX_TAG | JSON_HEX_AMP) – keine <, > oder & in der Ausgabe, sicher in
 *    <script type="application/json">. JSON enthält immer Anführungszeichen; in einem Attribut ginge es ohne e() ohnehin
 *    sofort kaputt – die Markierung „has_quotes“ sagt hier also nichts und fällt ebenfalls weg.
 */
final class TaintPlugin implements PluginEntryPointInterface, AddTaintsInterface, RemoveTaintsInterface
{
    private const REQUEST = 'Core\\Http\\Request';
    private const REQUEST_METHODS = ['input', 'str', 'host'];
    private const REQUEST_PROPS = ['query', 'post', 'files', 'server'];

    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        $registration->registerHooksFromClass(self::class);
    }

    public static function addTaints(AddRemoveTaintsEvent $event): array
    {
        $expr = $event->getExpr();
        if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall) {
            if ($expr->name instanceof Identifier && in_array(strtolower($expr->name->name), self::REQUEST_METHODS, true)
                && self::isRequest($event, $expr->var)) {
                return TaintKindGroup::ALL_INPUT;
            }
        }
        if ($expr instanceof Expr\PropertyFetch || $expr instanceof Expr\NullsafePropertyFetch) {
            if ($expr->name instanceof Identifier && in_array($expr->name->name, self::REQUEST_PROPS, true)
                && self::isRequest($event, $expr->var)) {
                return TaintKindGroup::ALL_INPUT;
            }
        }
        return [];
    }

    public static function removeTaints(AddRemoveTaintsEvent $event): array
    {
        $expr = $event->getExpr();
        if ($expr instanceof Expr\StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier
            && ltrim($expr->class->toString(), '\\') === 'Core\\Sanitizer'
            && in_array(strtolower($expr->name->name), ['inline', 'block'], true)) {
            return [TaintKind::INPUT_HTML, TaintKind::INPUT_HAS_QUOTES];
        }
        if ($expr instanceof Expr\FuncCall && $expr->name instanceof Name && strtolower($expr->name->getLast()) === 'json_encode'
            && !$expr->isFirstClassCallable() && isset($expr->getArgs()[1])) {
            $flags = $event->getStatementsSource()->getNodeTypeProvider()->getType($expr->getArgs()[1]->value);
            if ($flags && $flags->isSingleIntLiteral()) {
                $v = $flags->getSingleIntLiteral()->value;
                if (($v & JSON_HEX_TAG) && ($v & JSON_HEX_AMP)) return [TaintKind::INPUT_HTML, TaintKind::INPUT_HAS_QUOTES];
            }
        }
        return [];
    }

    private static function isRequest(AddRemoveTaintsEvent $event, Expr $var): bool
    {
        $type = $event->getStatementsSource()->getNodeTypeProvider()->getType($var);
        if (!$type) return false;
        foreach ($type->getAtomicTypes() as $atomic) {
            if ($atomic instanceof \Psalm\Type\Atomic\TNamedObject && strcasecmp($atomic->value, self::REQUEST) === 0) return true;
        }
        return false;
    }
}
