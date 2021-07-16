<?php

namespace Splashsky;

/**
 * Modello
 * 
 * A simple, ultra-lightweight template engine in PHP, for
 * small projects
 * 
 * @author Skylear "Splashsky" Johnson
 */

class Modello
{
    private string $directory;
    private string $extension;
    private array $values;
    private string $workingFile;

    /**
     * Create the instance of Modello
     * 
     * @param string $directory
     * @param string $ext
     * @return void
     */
    public function __construct(string $directory = '', string $ext = '.php')
    {
        $this->directory = $directory;
        $this->extension = $ext;

        $this->createCache();
    }

    /**
     * Find a given template using the currently stored directory
     * 
     * @param string $template
     * @return string
     */
    private function find(string $template)
    {
        $path = str_replace('.', '/', $template);
        $path = $this->directory . $path . $this->extension;
        $this->workingFile = $path;

        return $this->read($path);
    }

    /**
     * See if the file we're looking for is readable, if not then
     * we'll return an error string to ensure the user knows.
     * 
     * @param string $path
     * @return string
     */
    private function read(string $path)
    {
        return file_get_contents($path);
    }

    /**
     * Takes the path of the template and the values from bake()
     * and parses the template, caches a compiled version and returns
     * it.
     * 
     * @param string $template
     * @param array $values
     * @return string
     */
    private function parse(string $template, array $values = [])
    {
        $this->values = $values;

        $template = $this->processAllTags($template);

        $cache = $this->directory . '/cached';
        $cached = md5($this->workingFile).'.php';
        $file = $cache.'/'.$cached;

        /**
         * If the cached file doesn't exist, or has changed since it's last
         * compile, we'll write into the file with the new template
         */
        if (! is_readable($file) || md5($template) != md5_file($file)) {
            file_put_contents($file, $template);
        }
        
        /**
         * If there's an existing OB session we want to clear it out so we
         * can sandbox the template and process it.
         */
        if (ob_get_level() > 1) { ob_end_clean(); }
        ob_start();

        extract($values);
        require $file;

        return ob_get_clean();
    }

    /**
     * Send the provided string through all our tag parsing functions
     * 
     * @param string $template
     * @return string
     */
    private function processAllTags(string $template)
    {
        /**
         * The almighty include tag
         */
        $template = preg_replace_callback('/@include\(\s*(.+)\s*\)/', [$this, 'parseIncludeTag'], $template);

        /**
         * Echo tag (e.g. {{ $var }})
         */
        $template = preg_replace_callback('/{{\s*(.+?)\s*}}/', [$this, 'parseEchoTag'], $template);

        /**
         * If statement tags (e.g. @if(condition) ... @endif)
         */
        $template = preg_replace_callback('/@if\(\s*(.+)\s*\)/', [$this, 'parseIfTag'], $template);
        $template = preg_replace_callback('/@else/', [$this, 'parseElseTag'], $template);
        $template = preg_replace_callback('/@elseif\(\s*(.+)\s*\)/', [$this, 'parseElseIfTag'], $template);
        $template = preg_replace_callback('/@endif/', [$this, 'parseClosingBrace'], $template);

        /**
         * Loop statement tags (e.g. @foreach($values as $key => $value) ... @endforeach)
         */
        $template = preg_replace_callback('/@foreach\(\s*(.+)\s*\)/', [$this, 'parseForeachTag'], $template);
        $template = preg_replace_callback('/@endforeach/', [$this, 'parseClosingBrace'], $template);

        /**
         * Comment tags
         */
        $template = preg_replace_callback('/{--[\s\S]*?--}/', [$this, 'parseIntoNonexistence'], $template);

        return $template;
    }

    /**
     * Parse the echo tags in the template (e.g. {{ $foo }} becomes <?php echo($foo); ?>)
     * 
     * @param array $match
     * @return string
     */
    private function parseEchoTag(array $match) : string
    {
        return "<?php echo htmlentities({$match[1]}); ?>";
    }

    /**
     * Parse the if tags in the template (e.g. @if(condition) becomes <?php if($condition) { ?>)
     * 
     * @param array $match
     * @return string
     */
    private function parseIfTag(array $match) : string
    {
        return "<?php if({$match[1]}) { ?>";
    }

    /**
     * Parse the else tag for if statements! (e.g. @else becomes <?php } else { ?>)
     * 
     * @param array $match
     * @return string
     */
    private function parseElseTag(array $match) : string
    {
        return "<?php } else { ?>";
    }

    /**
     * Parse the else tag for if statements! (e.g. @else becomes <?php } else { ?>)
     * 
     * @param array $match
     * @return string
     */
    private function parseElseIfTag(array $match) : string
    {
        return "<?php } elseif({$match[1]}) { ?>";
    }

    /**
     * Parse the foreach tags in the template (e.g. @foreach(assignment) becomes <?php foreach(assignment) { ?>)
     * 
     * @param array $match
     * @return string
     */
    private function parseForeachTag(array $match) : string
    {
        return "<?php foreach({$match[1]}) { ?>";
    }

    /**
     * Parse the include tag so that it brings in the requested template
     * 
     * @param array $match
     * @return string
     */
    private function parseIncludeTag(array $match) : string
    {
        $path = str_replace('.', '/', $match[1]);
        $path = $this->directory . trim($path, "'") . $this->extension;

        return $this->read($path);
    }

    /**
     * Parse a tag into a closing brace for control structures
     * 
     * @param array $match
     * @return string
     */
    private function parseIntoNonexistence(array $match) : string
    {
        return "";
    }

    /**
     * Parse a tag into NONEXISTENCE (for comment tags)
     * 
     * @param array $match
     * @return string
     */
    private function parseClosingBrace(array $match) : string
    {
        return "<?php } ?>";
    }

    /**
     * Create the "cached" folder in the template directory if it doesn't exist
     * 
     * @return void
     */
    private function createCache()
    {
        $dir = $this->directory;

        if (! file_exists($dir.'/cached')) {
            mkdir($dir.'/cached');
        }
    }

    /**
     * This is the primary run function - it's given a path to the template
     * relative to the class' $directory, and an optional array of values
     * to pass to the template
     * 
     * @param string $template
     * @param array $values
     * @return string
     */
    public function bake(string $template, array $values = [])
    {
        $template = $this->find($template);
        return $this->parse($template, $values);
    }

    /**
     * A quick and easy static function for when you want to parse a string
     * without all the fancy rules and stuff. Parses tags like {{ foo }}
     * with values provided, e.g. ['foo' => 'bar']
     * 
     * @param string $template
     * @param array $values
     * @return $string
     */
    public static function simple(string $template, array $values = [])
    {
        if (is_readable($template)) { $template = file_get_contents($template); }

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_-]+)\s*}}/',
            function($match) use ($values) {
                return isset($values[$match[1]]) ? $values[$match[1]] : $match[0];
            },
            $template
        );
    }
}