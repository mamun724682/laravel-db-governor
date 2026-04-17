<?php

namespace Mamun724682\DbGovernor\Enums;

enum QueryStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
    case RolledBack = 'rolled_back';
    case Blocked = 'blocked';
}
