<?php

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database/Connection.php';
require_once __DIR__ . '/Auth/Session.php';
require_once __DIR__ . '/Auth/Csrf.php';
require_once __DIR__ . '/Auth/Authenticator.php';
require_once __DIR__ . '/Review/MergeQueue.php';
require_once __DIR__ . '/Review/ReviewRepository.php';

start_app_session();