<?php

namespace PlugHacker\PlugCore\Kernel\Services;

use PlugHacker\PlugCore\Kernel\Abstractions\AbstractI18NTable;
use PlugHacker\PlugCore\Kernel\Abstractions\AbstractModuleCoreSetup;

final class LocalizationService
{
    const DEFAULT_LOCALE = 'en_US';

    /**
     *
     * @param mixed Variable num of params.
     */
    public function getDashboard($string)
    {
        $numArgs = func_num_args();
        $args = [
            $this->translateDashboard($string)
        ];
        for ($i = 1; $i < $numArgs; $i++) {
            $args[$i] = func_get_arg($i);
        }
        $test = call_user_func_array('sprintf', $args);
        return $test;
    }

    private function translateDashboard($string)
    {
        $locale = AbstractModuleCoreSetup::getDashboardLanguage();
        /**
         *
 * @var AbstractI18NTable $i18nTable
*/
        $i18nTable = $this->getI18NTableOrDefaultFor($locale);

        if ($i18nTable === null) {
            return $string;
        }

        $result = $i18nTable->get($string);
        if ($result === null) {
            return $string;
        }

        return $result;
    }

    private function getI18NTableOrDefaultFor($locale)
    {
        $langClass = str_replace('_', '', $locale);
        $langClass = strtoupper($langClass);
        $langClass = "PlugHacker\\PlugCore\\Kernel\\I18N\\$langClass";

        if (class_exists($langClass)) {
            return new $langClass();
        }

        if ($locale === self::DEFAULT_LOCALE) {
            return null;
        }

        return $this->getI18NTableOrDefaultFor(self::DEFAULT_LOCALE);
    }
}
