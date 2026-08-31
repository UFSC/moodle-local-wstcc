<?php
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// Sem bump o Moodle nao rele o db/services.php, e a funcao nova continua
// respondendo invalidrecord no webservice.
// Mantido o formato de 12 digitos do valor anterior (201811090703): um numero
// mais curto seria MENOR e o upgrade nunca rodaria.
$plugin->version  = 202608310200;
$plugin->cron     = false;
$plugin->maturity = MATURITY_STABLE;
$plugin->component = 'local_wstcc';