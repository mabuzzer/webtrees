<?php namespace Fisharebest\Localization\Locale;

use Fisharebest\Localization\Territory\TerritoryMl;

/**
 * Class LocaleFrMl
 *
 * @author    Greg Roach <fisharebest@gmail.com>
 * @copyright (c) 2018 Greg Roach
 * @license   GPLv3+
 */
class LocaleFrMl extends LocaleFr
{
    public function territory()
    {
        return new TerritoryMl();
    }
}
