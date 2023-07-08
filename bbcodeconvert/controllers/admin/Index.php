<?php
/**
 * @copyright Ilch 2
 * @package ilch
 */

namespace Modules\Bbcodeconvert\Controllers\Admin;

use Ilch\Controller\Admin;
use Modules\Admin\Mappers\Module as ModuleMapper;
use Modules\Bbcodeconvert\Mappers\Texts as TextsMapper;
use Modules\Bbcodeconvert\Mappers\User as UserMapper;
use Modules\Admin\Mappers\Backup as BackupMapper;

class Index extends Admin
{
    private const supportedModules = [
        'contact' => ['2.1.50'],
        'events' => ['1.21.2', '1.22.0'],
        'forum' => ['1.31.0', '1.32.0'],
        'guestbook' => ['1.12.0', '1.13.0'],
        'jobs' => ['1.5.0', '1.6.0'],
        'teams' => ['1.22.0', '1.23.0'],
        'user' => ['2.1.50']
    ];

    // Number of items per batch (work is splitted up).
    private const batch = 100;

    public function init()
    {
        $items = [
            [
                'name' => 'menuOverview',
                'active' => false,
                'icon' => 'fa-solid fa-arrow-right-arrow-left',
                'url' => $this->getLayout()->getUrl(['controller' => 'index', 'action' => 'index'])
            ],
            [
                'name' => 'menuNote',
                'active' => false,
                'icon' => 'fa-solid fa-circle-info',
                'url' => $this->getLayout()->getUrl(['controller' => 'index', 'action' => 'note'])
            ]
        ];

        if ($this->getRequest()->getActionName() === 'note') {
            $items[1]['active'] = true;
        } else {
            $items[0]['active'] = true;
        }

        $this->getLayout()->addMenu(
            'menuConvert',
            $items
        );
    }

    public function indexAction()
    {
        $moduleMapper = new ModuleMapper();
        $backupMapper = new BackupMapper();

        $this->getLayout()->getAdminHmenu()
            ->add($this->getTranslator()->trans('menuConvert'), ['action' => 'index'])
            ->add($this->getTranslator()->trans('menuOverview'), ['action' => 'index']);

        $installedSupportedModules = [];
        $_SESSION['bbcodeconvert_modulesToConvert'] = [];
        $modules = $moduleMapper->getModules();

        foreach ($modules as $module) {
            // Check if the version of the module is supported. For system modules this is the ilch version.
            if (array_key_exists($module->getKey(), self::supportedModules) && (in_array($module->getVersion(), self::supportedModules[$module->getKey()]) || ($module->getSystemModule() && in_array(VERSION, self::supportedModules[$module->getKey()])))) {
                if ($module->getSystemModule() && empty($module->getVersion())) {
                    $module->setVersion(VERSION);
                }

                $installedSupportedModules[] = $module;
            }
        }

        if ($this->getRequest()->getPost('action') === 'convert' && $this->getRequest()->getPost('check_modules')) {
            foreach ($this->getRequest()->getPost('check_modules') as $moduleKey) {
                $_SESSION['bbcodeconvert_modulesToConvert'][] = ['module' => $moduleKey, 'currentTask' => '', 'completed' => false, 'index' => 0, 'progress' => 0, 'count' => $this->getCount($moduleKey)];
            }
            $this->redirect($this->getView()->getUrl(['action' => 'convert'], null, true));
        }

        $this->getView()->set('installedSupportedModules', $installedSupportedModules)
            ->set('maintenanceModeEnabled', $this->getConfig()->get('maintenance_mode'))
            ->set('lastBackup', $backupMapper->getLastBackup())
            ->set('convertedModules', (json_decode($this->getConfig()->get('bbcodeconvert_convertedModules'), true)) ?? []);
    }

    public function convertAction()
    {
        if (!$this->getRequest()->isSecure()) {
            $this->redirect(['action' => 'index']);
            return;
        }

        $time_start = microtime(true);
        @set_time_limit(300);
        $workDone = true;

        foreach ($_SESSION['bbcodeconvert_modulesToConvert'] as $key => $module) {
            if (!$module['completed']) {
                $workDone = false;
                $result = $this->convert($module['module'], $module['index'], $module['progress']);

                if (!empty($result)) {
                    $_SESSION['bbcodeconvert_modulesToConvert'][$key] = array_merge($_SESSION['bbcodeconvert_modulesToConvert'][$key], $this->convert($module['module'], $module['index'], $module['progress'], $module['currentTask']));

                    // Exit this loop to not reach max_execution_time inside this function. This function gets called again by a javascript redirect.
                    $this->getView()->set('redirectAfterPause', true);
                    break;
                }
            }
        }

        $convertedModules = [];
        foreach($_SESSION['bbcodeconvert_modulesToConvert'] as $value) {
            $convertedModules[$value['module']] = $value['completed'];
        }

        $knownConvertedModules = (json_decode($this->getConfig()->get('bbcodeconvert_convertedModules'), true)) ?? [];
        $knownConvertedModules = array_merge($knownConvertedModules, $convertedModules);
        $this->getConfig()->set('bbcodeconvert_convertedModules', json_encode($knownConvertedModules));
        $time_end = microtime(true);

        $this->getView()->set('time', $time_end - $time_start)
            ->set('workDone', $workDone);
    }

    public function noteAction()
    {
        $this->getLayout()->getAdminHmenu()
            ->add($this->getTranslator()->trans('menuConvert'), ['action' => 'index'])
            ->add($this->getTranslator()->trans('menuNote'), ['action' => 'note']);

        $this->getView()->set('supportedModules', self::supportedModules);
    }

    /**
     * Get number of items to convert.
     *
     * @param string $moduleKey
     * @return int
     */
    public function getCount(string $moduleKey): int
    {
        $textsMapper = new TextsMapper();

        switch ($moduleKey) {
            case 'admin':
                break;
            case 'contact':
                // table: config, column: value, datatype: VARCHAR(191)
                return 1;
            case 'events':
                // table: events, column: text, datatype: LONGTEXT
                $textsMapper->table = 'events';
                break;
            case 'forum':
                // table: forum_posts, column: text, datatype: TEXT
                $textsMapper->table = 'forum_posts';
                break;
            case 'guestbook':
                // table: gbook, column: text, datatype: MEDIUMTEXT
                $textsMapper->table = 'gbook';
                break;
            case 'jobs':
                // table: jobs, column: text, datatype: MEDIUMTEXT
                $textsMapper->table = 'jobs';
                break;
            case 'teams':
                // table: teams_joins, column: text, datatype: LONGTEXT
                $textsMapper->table = 'teams_joins';
                break;
            case 'user':
                // table: users, column: signature, datatype: VARCHAR
                // table: users_dialog_reply, column: reply, datatype: TEXT
                $userMapper = new UserMapper();

                return $userMapper->getCount();
        }

        return $textsMapper->getCount();
    }

    /**
     * Convert from bbcode to html.
     *
     * @param string $moduleKey
     * @param int $index
     * @param int $progress
     * @param string $currentTask
     * @return array
     * @see https://dev.mysql.com/doc/refman/8.0/en/storage-requirements.html#data-types-storage-reqs-strings
     */
    public function convert(string $moduleKey, int $index, int $progress, string $currentTask = ''): array
    {
        switch ($moduleKey) {
            case 'admin':
                break;
            case 'contact':
                // table: config, column: value, datatype: VARCHAR(191)
                $convertedText = $this->getView()->getHtmlFromBBCode($this->getConfig()->get('contact_welcomeMessage'));

                if (strlen($convertedText) <= 191) {
                    $this->getConfig()->set('contact_welcomeMessage', $convertedText);
                }
                return ['completed' => true, 'index' => 0, 'progress' => 1];
            case 'events':
                // table: events, column: text, datatype: LONGTEXT
                $textsMapper = new TextsMapper();
                $textsMapper->table = 'events';
                $texts = $textsMapper->getTexts($index, self::batch);

                foreach($texts as $text) {
                    $convertedText = $this->getView()->getHtmlFromBBCode($text['text']);
                    // L + 4 bytes, where L < 2^32
                    if (strlen($convertedText) <= ((4294967295 / 5) - 4)) {
                        $textsMapper->updateText($text['id'], $convertedText);
                    }
                }

                if (empty($texts)) {
                    return ['completed' => true, 'index' => $index];
                }

                return ['completed' => false, 'index' => $index + count($texts), 'progress' => $index + count($texts)];
            case 'forum':
                // table: forum_posts, column: text, datatype: TEXT
                $textsMapper = new TextsMapper();
                $textsMapper->table = 'forum_posts';
                $texts = $textsMapper->getTexts($index, self::batch);

                foreach($texts as $text) {
                    $convertedText = $this->getView()->getHtmlFromBBCode($text['text']);

                    // L + 2 bytes, where L < 2^16
                    if (strlen($convertedText) <= ((65535 / 5) - 2)) {
                        $textsMapper->updateText($text['id'], $convertedText);
                    }
                }

                if (empty($texts)) {
                    return ['completed' => true, 'index' => $index];
                }

                return ['completed' => false, 'index' => $index + count($texts), 'progress' => $index + count($texts)];
            case 'guestbook':
                // table: gbook, column: text, datatype: MEDIUMTEXT
                $textsMapper = new TextsMapper();
                $textsMapper->table = 'gbook';
                $texts = $textsMapper->getTexts($index, self::batch);

                foreach($texts as $text) {
                    $convertedText = $this->getView()->getHtmlFromBBCode($text['text']);

                    // L + 3 bytes, where L < 2^24
                    if (strlen($convertedText) <= ((16777215 / 5) - 3)) {
                        $textsMapper->updateText($text['id'], $convertedText);
                    }
                }

                if (empty($texts)) {
                    return ['completed' => true, 'index' => $index];
                }

                return ['completed' => false, 'index' => $index + count($texts), 'progress' => $index + count($texts)];
            case 'jobs':
                // table: jobs, column: text, datatype: MEDIUMTEXT
                $textsMapper = new TextsMapper();
                $textsMapper->table = 'jobs';
                $texts = $textsMapper->getTexts($index, self::batch);

                foreach($texts as $text) {
                    $convertedText = $this->getView()->getHtmlFromBBCode($text['text']);

                    // L + 3 bytes, where L < 2^24
                    if (strlen($convertedText) <= ((16777215 / 5) - 3)) {
                        $textsMapper->updateText($text['id'], $convertedText);
                    }
                }

                if (empty($texts)) {
                    return ['completed' => true, 'index' => $index];
                }

                return ['completed' => false, 'index' => $index + count($texts), 'progress' => $index + count($texts)];
            case 'teams':
                // table: teams_joins, column: text, datatype: LONGTEXT
                $textsMapper = new TextsMapper();
                $textsMapper->table = 'teams_joins';
                $texts = $textsMapper->getTexts($index, self::batch);

                foreach($texts as $text) {
                    $convertedText = $this->getView()->getHtmlFromBBCode($text['text']);

                    // L + 3 bytes, where L < 2^24
                    if (strlen($convertedText) <= ((16777215 / 5) - 3)) {
                        $textsMapper->updateText($text['id'], $convertedText);
                    }
                }

                if (empty($texts)) {
                    return ['completed' => true, 'index' => $index];
                }

                return ['completed' => false, 'index' => $index + count($texts), 'progress' => $index + count($texts)];
            case 'user':
                $userMapper = new UserMapper();

                if ($currentTask === '' || $currentTask === 'signature') {
                    // table: users, column: signature, datatype: VARCHAR
                    $signatures = $userMapper->getSignatures($index, self::batch);

                    foreach($signatures as $signature) {
                        $convertedSignature = $this->getView()->getHtmlFromBBCode($signature['signature']);

                        // L + 2 bytes, where L < 2^16
                        if (strlen($convertedSignature) <= ((65535 / 5) - 2)) {
                            $userMapper->updateSignature($signature['id'], $convertedSignature);
                        }
                    }

                    if (!empty($signatures) && $currentTask === 'signature') {
                        return ['currentTask' => 'signature', 'completed' => false, 'index' => $index + count($signatures), 'progress' => $index + count($signatures)];
                    }

                    if (empty($signatures)) {
                        // Reset index to 0 for next task.
                        $index = 0;
                    }
                }

                // table: users_dialog_reply, column: reply, datatype: TEXT
                $replies = $userMapper->getReplies($index, self::batch);

                foreach($replies as $reply) {
                    $convertedReply = $this->getView()->getHtmlFromBBCode($reply['reply']);

                    // L + 2 bytes, where L < 2^16
                    if (strlen($convertedReply) <= ((65535 / 5) - 2)) {
                        $userMapper->updateReply($reply['id'], $convertedReply);
                    }
                }

                if (empty($replies)) {
                    return ['currentTask' => 'reply', 'completed' => true, 'index' => $index];
                }

                return ['currentTask' => 'reply', 'completed' => false, 'index' => $index + count($replies), 'progress' => $progress + count($replies)];
        }

        return [];
    }
}
