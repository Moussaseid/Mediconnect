<?php
namespace tests\unit;

use PHPUnit\Framework\TestCase;
use controllers\BaseController;

/**
 * Tests unitaires — Protection CSRF (Issue #3 #4 #5 #6)
 *
 * genererTokenCsrf() et verifierTokenCsrf() sont protected dans BaseController.
 * On les expose via une sous-classe de test. redirect() est surchargée pour
 * lever une exception au lieu d'appeler header().
 *
 * Couvre :
 *  - Token généré est une chaîne hex de 64 caractères (random_bytes(32))
 *  - Token stocké en session après génération
 *  - Token idempotent : même token si déjà en session
 *  - Vérification réussie avec token valide (pas d'exception)
 *  - Rotation du token après vérification réussie (supprimé de la session)
 *  - Vérification échoue (→ 403 + redirect) si token POST absent
 *  - Vérification échoue si token POST invalide
 *  - Vérification échoue si aucun token en session
 */
class CsrfTest extends TestCase
{
    private function makeController(): object
    {
        return new class extends BaseController {
            public function exposerGenerer(): string
            {
                return $this->genererTokenCsrf();
            }

            public function exposerVerifier(): void
            {
                $this->verifierTokenCsrf();
            }

            protected function redirect(string $url): never
            {
                throw new \RuntimeException('REDIRECT:' . $url);
            }
        };
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST    = [];
    }

    // ----------------------------------------------------------------- génération

    public function testTokenGenereEstUneChaineHex64(): void
    {
        $ctrl  = $this->makeController();
        $token = $ctrl->exposerGenerer();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testTokenEstStockeEnSession(): void
    {
        $ctrl = $this->makeController();
        $ctrl->exposerGenerer();
        $this->assertArrayHasKey('csrf_token', $_SESSION);
    }

    public function testTokenIdempotentSiDejaEnSession(): void
    {
        $ctrl = $this->makeController();
        $t1   = $ctrl->exposerGenerer();
        $t2   = $ctrl->exposerGenerer();
        $this->assertSame($t1, $t2, 'Un nouveau token ne doit pas être généré si la session en contient déjà un.');
    }

    // ----------------------------------------------------------------- vérification — succès

    public function testVerificationReussieAvecTokenValide(): void
    {
        $_SESSION['csrf_token'] = 'token_valide_test';
        $_POST['csrf_token']    = 'token_valide_test';

        $ctrl = $this->makeController();
        $ctrl->exposerVerifier(); // Aucune exception attendue
        $this->assertTrue(true);
    }

    public function testTokenSupprimeDeLaSessionApresVerificationReussie(): void
    {
        $_SESSION['csrf_token'] = 'rotation_test';
        $_POST['csrf_token']    = 'rotation_test';

        $ctrl = $this->makeController();
        $ctrl->exposerVerifier();

        $this->assertArrayNotHasKey('csrf_token', $_SESSION, 'Token doit être supprimé après vérification (rotation).');
    }

    // ----------------------------------------------------------------- vérification — échec

    public function testVerificationEchoueSiTokenPostAbsent(): void
    {
        $_SESSION['csrf_token'] = 'token_en_session';
        // $_POST['csrf_token'] absent

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposerVerifier();
    }

    public function testVerificationEchoueAvecTokenInvalide(): void
    {
        $_SESSION['csrf_token'] = 'token_attendu';
        $_POST['csrf_token']    = 'token_invalide';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposerVerifier();
    }

    public function testVerificationEchoueSiAucunTokenEnSession(): void
    {
        // Session vide, token soumis quelconque
        $_POST['csrf_token'] = 'nImporteQuoi';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposerVerifier();
    }

    public function testVerificationEchoueSiTokensIdentiquesVidesOuNuls(): void
    {
        // empty string dans les deux = empty($tokenAttendu) => true => 403
        $_SESSION['csrf_token'] = '';
        $_POST['csrf_token']    = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposerVerifier();
    }
}
