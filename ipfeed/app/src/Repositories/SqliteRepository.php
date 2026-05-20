<?php
declare(strict_types=1);

namespace IpFeed\Repositories;

use PDO;

abstract class SqliteRepository
{
    public function __construct(protected readonly PDO $db)
    {
    }
}
