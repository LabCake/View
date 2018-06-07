<?php

/**
 * A simple view parser
 * @author LabCake
 * @copyright 2018 LabCake
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

namespace LabCake;

class View
{
    /**
     * @var string
     */
    static $s_php_open_tag = "<?php";
    /**
     * @var string
     */
    static $s_php_close_tag = "?>";
    /**
     * @var array
     */
    static $s_static_variables = array();

    /**
     * @var null|string
     */
    private $m_file;
    /**
     * @var array
     */
    private $m_paths = array();
    /**
     * @var array
     */
    private $m_variables = array();
    /**
     * @var null|View
     */
    private $m_parent = null;

    /**
     * @var array
     */
    static $s_options = array(
        'error' => true,        ///-- Error Reporting
        'error.exit' => true,   ///-- Quit on Error
        'root' => ''
    );

    function __construct($file = null)
    {
        $this->m_file = $file;

        $this->m_variables['_CWD'] = getcwd();
        $this->m_variables['_FILE'] =& $this->m_file;
        $this->m_variables['_SELF'] = $_SERVER['PHP_SELF'];
        $this->m_variables['_URI'] = $_SERVER['REQUEST_URI'];
        $this->m_variables['_ROOT'] =& self::$s_options['root'];
    }

    final static public function SetOption($option, $value)
    {
        $option = strtolower($option);
        if (array_key_exists($option, self::$s_options)) {
            $option_value = self::$s_options[$option];
            if ((is_bool($option_value) && !is_bool($value)) || (is_long($option_value) && !is_long($value)) || (is_string($option_value) && !is_string($value))) {
                return self::__error("Illegal option value");
            }
            self::$s_options[$option] = $value;
        } else {
            return self::__error("No such option '%s'", $option);
        }
        return true;
    }

    /**
     * @param $root
     */
    public function addPath($root)
    {
        if (is_array($root)) {
            foreach ($root as $item) {
                $this->addPath($item);
                return;
            }
        }
        $this->m_paths[] = $root;
    }

    /**
     * @param View $parent
     */
    public function SetParent($parent)
    {
        $this->m_parent = $parent;
    }

    /**
     * @param $name
     * @return $this|View|null
     */
    final public function __find($name)
    {
        if (array_key_exists($name, $this->m_variables)) {
            return $this;
        } else {
            if ($this->m_parent) {
                return $this->m_parent->__find($name);
            }
        }
        return null;
    }

    /**
     * @param $name
     * @param $value
     */
    final public function __set($name, $value)
    {
        $name = strtoupper($name);
        if ($value instanceof View) {
            $value->SetParent($this);
        }
        if ($this->m_parent) {
            $owner = $this->m_parent->__find($name);
            if ($owner) {
                $owner->__set_explicit($name, $value);
                return;
            }
        }
        $this->__set_explicit($name, $value);
    }

    /**
     * @param $name
     * @param $value
     */
    final public function Set($name, $value)
    {
        $this->__set($name, $value);
    }

    /**
     * @param $name
     * @return mixed|null
     */
    final public function Get($name)
    {
        return $this->__get($name);
    }

    /**
     * @param $name
     * @param $value
     */
    final public function __set_explicit($name, $value)
    {
        $this->m_variables[$name] = $value;
    }

    /**
     * @param $name
     * @param $value
     */
    public static function SetVariable($name, $value)
    {
        $name = strtoupper($name);
        self::$s_static_variables[$name] = $value;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    final public function &__get($name)
    {
        $null = null;
        $name = strtoupper($name);
        if (array_key_exists($name, $this->m_variables)) {
            return $this->m_variables[$name];
        } else {
            if ($this->m_parent) {
                return $this->m_parent->__get($name);
            } else {
                if (array_key_exists($name, self::$s_static_variables)) {
                    return self::$s_static_variables[$name];
                }
            }
        }
        return $null;
    }


    final static function __error()
    {
        if (self::$s_options['error']) {
            $debug = debug_backtrace();
            if ($debug && isset($debug[1])) {
                if (func_num_args()) {
                    $args = func_get_args();
                    $format = array_shift($args);
                    $func_args = "";
                    $arg_count = count($debug[1]['args']);
                    for ($i = 0; $i < $arg_count; $i++) {
                        $func_args .= var_export($debug[1]['args'][$i], true);
                        if ($i != ($arg_count - 1)) {
                            $func_args .= "<b>,</b> ";
                        }
                    }
                    printf("<div style='background: #f8d7da; padding: 10px 18px; border: 1px solid #f5c6cb; color: #721c24;'><strong>%s</strong>::%s<strong>(</strong>%s<strong>):</strong> %s</div>",
                        $debug[1]['class'], $debug[1]['function'], $func_args, vsprintf($format, $args));
                    if (self::$s_options['error.exit']) {
                        exit;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param null $file
     * @return string
     */
    public function Parse($file = null)
    {
        if ($file === null)
            $file = $this->m_file;

        if (!$file)
            self::__error("No file selected");

        $paths = array(self::$s_options['root']);
        $paths = array_merge($this->m_paths, $paths);

        $filePath = null;
        foreach ($paths as $path) {
            $f = sprintf("%s%s", $path, $file);
            if (file_exists($f))
                $filePath = $f;
        }

        if (!$filePath)
            self::__error("View file \"%s\" not found", $file);

        ob_start();
        include($filePath);
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * @param $file
     * @param array $args
     */
    final private function includeFile($file, $args = array())
    {
        $view = new View();
        $view->SetParent($this);
        foreach ($args as $key => $value) {
            $view->Set($key, $value);
        }

        $view->Display($file);
    }

    /**
     * @param null $file
     */
    public function Display($file = null)
    {
        echo $this->Parse($file);
    }
}