<?php

namespace App\Modules\Payout\Exceptions;

use RuntimeException;

/**
 * Falha de regra de negócio em pagamentos (mensagem exibível ao usuário).
 */
class PayoutException extends RuntimeException {}
