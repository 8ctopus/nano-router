<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

enum RouteType
{
    case Exact;
    case StartsWith;
    case Regex;
}
