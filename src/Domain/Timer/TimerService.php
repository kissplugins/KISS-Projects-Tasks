<?php

namespace KISS\PTT\Domain\Timer;

use KISS\PTT\Integration\ACF\ACFAdapter;
use KISS\PTT\Domain\Session\SessionRepository;

/**
 * Timer orchestration: start/stop/resume with validation.
 * This is a thin domain layer; handlers/controllers should call into this.
 */
class TimerService
{
    private ACFAdapter $acf;
    private SessionRepository $sessions;

    public function __construct(ACFAdapter $acf, SessionRepository $sessions)
    {
        $this->acf = $acf;
        $this->sessions = $sessions;
    }

    /** Start a new session on a task with an initial title. */
    public function start(int $postId, string $title): bool
    {
        // Basic invariant: no overlapping parent-level timer on this task
        $start = $this->acf->getField('start_time', $postId);
        $stop  = $this->acf->getField('stop_time', $postId);
        if ($start && !$stop) {
            return false;
        }

        $now = $this->acf->nowUtc();
        $row = [
            'session_title'           => $title,
            'session_start_time'      => $now,
            'session_stop_time'       => '',
            'session_manual_override' => 0,
            'session_manual_duration' => 0,
        ];
        return $this->sessions->add($row, $postId);
    }

    /** Stop the active (running) session for a task, if any. */
    public function stopActive(int $postId): bool
    {
        $count = $this->sessions->count($postId);
        if ($count <= 0) { return false; }
        $now = $this->acf->nowUtc();
        // Walk backward for running session
        $sessions = $this->sessions->getAll($postId);
        for ($i = $count - 1; $i >= 0; $i--) {
            $s = $sessions[$i] ?? [];
            if (!empty($s['session_start_time']) && empty($s['session_stop_time'])) {
                return $this->sessions->updateSub($i, 'session_stop_time', $now, $postId);
            }
        }
        return false;
    }
}

