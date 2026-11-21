<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

/** Abgewiesenes Token; die Meldung ist ein kurzer Grund (format, alg, signature, unknown_kid, iss, aud, exp, nonce …) – nie Inhalte */
final class JwtException extends \RuntimeException {}
