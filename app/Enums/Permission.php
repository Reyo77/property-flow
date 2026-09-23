<?php

namespace App\Enums;

enum Permission: string
{
    case ViewCommunities = 'communities.view';
    case ManageCommunities = 'communities.manage';
    case ViewBuildings = 'buildings.view';
    case ManageBuildings = 'buildings.manage';
    case ViewUnits = 'units.view';
    case ManageUnits = 'units.manage';
    case ImportUnits = 'units.import';
}
