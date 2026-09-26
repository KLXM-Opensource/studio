<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Sabre\DAV\PropPatch;

/** Principals: nur der angemeldete Benutzer selbst (keine Adressliste anderer Konten). */
final class Principals extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend
{
    private function me(): ?array
    {
        if (!Dav::$user) return null;
        $email = strtolower((string) Dav::$user['email']);
        return ['uri' => 'principals/' . $email, '{DAV:}displayname' => (string) (Dav::$user['name'] ?: $email),
            '{http://sabredav.org/ns}email-address' => $email];
    }

    public function getPrincipalsByPrefix($prefixPath)
    {
        return $prefixPath === 'principals' && ($me = $this->me()) ? [$me] : [];
    }

    public function getPrincipalByPath($path)
    {
        $me = $this->me();
        return $me && strtolower((string) $path) === $me['uri'] ? $me : null;
    }

    public function updatePrincipal($path, PropPatch $propPatch)
    {
    }

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        $me = $this->me();
        if (!$me || $prefixPath !== 'principals') return [];
        foreach ($searchProperties as $prop => $value) {
            $hit = isset($me[$prop]) && str_contains(strtolower((string) $me[$prop]), strtolower((string) $value));
            if ($test === 'anyof' && $hit) return [$me['uri']];
            if ($test === 'allof' && !$hit) return [];
        }
        return $test === 'allof' ? [$me['uri']] : [];
    }

    public function findByUri($uri, $principalPrefix)
    {
        $me = $this->me();
        return $me && strtolower((string) $uri) === 'mailto:' . strtolower((string) Dav::$user['email']) ? $me['uri'] : null;
    }

    public function getGroupMemberSet($principal)
    {
        return [];
    }

    public function getGroupMembership($principal)
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members)
    {
        throw new \Sabre\DAV\Exception\Forbidden('Gruppen werden im CMS verwaltet.');
    }
}
