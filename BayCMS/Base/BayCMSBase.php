<?php
namespace BayCMS\Base;

class BayCMSBase
{
    protected array $settings = [];
    protected \BayCMS\Base\BayCMSContext $context;
   
    public function setContext(\BayCMS\Base\BayCMSContext $context)
    {
        $this->context = $context;
    }
    public function get($key = null, $default = '')
    {
        if ($key === null)
            return $this->settings;
        return $this->settings[$key] ?? $default;
    }
    public function set(array $settings)
    {
        foreach ($settings as $k => $v) {
            $this->settings[$k] = $v;
        }
    }

    public function t(string $en, ?string $de = null, bool $save = false): string
    {
        return $this->context->t($en, $de, $save);
    }

    public function de(): bool
    {
        return $this->context->lang == 'de';
    }

    public static function nameToId($name)
    {
        return strtolower(preg_replace("/\\W/", '_', $name));
    }

}