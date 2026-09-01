<?php
/**
 * Phase 2 — LogoutTest
 *
 * Verifies auth_service::endSession() deletes the DB row and the
 * LogoutAction flow (logout is exercised via the Auth facade in this
 * test since the Action invokes session_destroy which terminates
 * the CLI process).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class LogoutTest extends Fixtures
{
    public function test_end_session_deletes_db_row(): void
    {
        $uid = $this->seedUser([
            'email' => 'logout@students.nsbm.ac.lk',
            'nickname' => 'logoutuser',
            'student_id' => 'NSBM/2024/LO1',
        ]);
        $sid = str_repeat('a', 48);
        $this->seedSession($sid, $uid);
        $this->assertNotNull(\App\Auth\Model\session_model::findById($this->pdo, $sid));

        auth_service::endSession($sid, $uid);
        $this->assertNull(\App\Auth\Model\session_model::findById($this->pdo, $sid));
    }

    public function test_end_session_only_deletes_own_session(): void
    {
        $uidA = $this->seedUser([
            'email' => 'a@students.nsbm.ac.lk',
            'nickname' => 'usera',
            'student_id' => 'NSBM/2024/LO2',
        ]);
        $uidB = $this->seedUser([
            'email' => 'b@students.nsbm.ac.lk',
            'nickname' => 'userb',
            'student_id' => 'NSBM/2024/LO3',
        ]);
        $sidA = str_repeat('a', 48);
        $sidB = str_repeat('b', 48);
        $this->seedSession($sidA, $uidA);
        $this->seedSession($sidB, $uidB);

        // User A's logout must not delete User B's session.
        auth_service::endSession($sidA, $uidA);
        $this->assertNull(\App\Auth\Model\session_model::findById($this->pdo, $sidA));
        $this->assertNotNull(\App\Auth\Model\session_model::findById($this->pdo, $sidB));
    }
}
