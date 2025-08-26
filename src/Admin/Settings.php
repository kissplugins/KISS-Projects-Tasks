<?php
namespace KISS\PTT\Admin;

class Settings {
    public const OPTION_FSM_ENABLED = 'ptt_fsm_enabled';
    public const OPTION_FSM_TODAY   = 'ptt_fsm_today_enabled';
    public const OPTION_FSM_EDITOR  = 'ptt_fsm_editor_enabled';

    public static function getFlags(): array {
        return [
            'enabled' => get_option(self::OPTION_FSM_ENABLED, '1') === '1',
            'today'   => get_option(self::OPTION_FSM_TODAY,   '1') === '1',
            'editor'  => get_option(self::OPTION_FSM_EDITOR,  '1') === '1',
        ];
    }

    public static function saveFlagsFromRequest(array $post): void {
        update_option(self::OPTION_FSM_ENABLED, isset($post['ptt_fsm_enabled']) ? '1' : '0');
        update_option(self::OPTION_FSM_TODAY,   isset($post['ptt_fsm_today_enabled']) ? '1' : '0');
        update_option(self::OPTION_FSM_EDITOR,  isset($post['ptt_fsm_editor_enabled']) ? '1' : '0');
    }
}

