<?php

namespace RSE\Delivra\Exceptions\Telegram;

/**
 * Telegram answered 403: the user blocked the bot, deleted the account, or the bot was removed from the chat.
 */
class BotBlockedException extends TelegramMessageFailedException {}
