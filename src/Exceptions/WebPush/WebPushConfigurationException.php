<?php

namespace RSE\Delivra\Exceptions\WebPush;

/**
 * Broken setup: minishlink/web-push is missing or the VAPID keys are not set.
 * The only web push failure that throws during a send.
 */
class WebPushConfigurationException extends WebPushException {}
