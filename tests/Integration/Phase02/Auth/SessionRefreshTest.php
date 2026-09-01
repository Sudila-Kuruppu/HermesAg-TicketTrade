<?php
/**
 * Phase 2 — SessionRefreshTest
 *
 * Verifies session_model::touch() bumps last_seen (the D-04 7-day
 * refresh-on-activity contract). The 5-min idempotency window lives
 * in Support\Auth::boot(); session_model::touch() is the low-level
 * writer.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Model\session_model;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class SessionRefreshTest extends Fixtures
{
    public function test_touch_updates_last_seen(): void
    {
        $uid = $this->seedUser([
            'email' => 'session@students.nsbm.ac.lk',
            'nickname' => 'sessionuser',
            'student_id' => 'NSBM/2024/SR1',
        ]);
        $sid = str_repeat('c', 48);
        $old = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->seedSession($sid, $uid, $old);

        $ok = session_model::touch($this->pdo, $sid);
        $this->assertTrue($ok);

        $row = session_model::findById($this->pdo, $sid);
        $this->assertNotNull($row);
        $oldTime = strtotime($old);
        $newTime = strtotime($row['last_seen']);
        $this->assertGreaterThan($oldTime, $newTime, 'last_seen was bumped');
    }

    public function test_delete_removes_session(): void
    {
        $uid = $this->seedUser([
            'email' => 'del@students.nsbm.ac.lk',
            'nickname' => 'deluser',
            'student_id' => 'NSBM/2024/SR2',
        ]);
        $sid = str_repeat('d', 48);
        $this->seedSession($sid, $uid);
        $this->assertTrue(session_model::delete($this->pdo, $sid, $uid));
        $this->assertNull(session_model::findById($this->pdo, $sid));
    }
}
