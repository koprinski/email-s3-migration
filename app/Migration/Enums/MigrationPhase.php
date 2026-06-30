<?php

namespace App\Migration\Enums;

enum MigrationPhase: string
{
    case Persist = 'persist';
    case Migrate = 'migrate';
}
