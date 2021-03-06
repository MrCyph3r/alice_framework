<?php namespace Alice\Core;

class BaseView
{
    private $viewFile;
    private $viewVariables;
    private $compiledView;

    public function __construct()
    {
        //echo "I'm BaseView.<br />";
    }

    /**
     * This method is used to recursively include compiled template partials.
     *
     * @param string $filePath The template to to compile.
     * @return string          The compiled data.
     */
    private function evalIncludes($filePath)
    {
        // Instead of throwing an exception, if file doesn't exists I just don't include it.
        if (file_exists($filePath))
        {
            // First of all I need to get the compiled template file.
            ob_start();

            include $filePath;
            $compiledData = ob_get_contents();

            ob_end_clean();

            // Now I need to check if the template file contains other include directives, and then recursively add the files.
            if (preg_match_all("/##\s+include\('(\S+)'\)\s+##/", $compiledData, $matches))
            {
                // Loop through all the include directives
                for ($i = 0; $i < count($matches[0]); $i++)
                {
                    $includePath = Application::getPath('path.views') . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $matches[1][$i]) . '.php';

                    // Verify that file exists.
                    if (file_exists($includePath))
                    {
                        // Recursively get the compiled template file.
                        $recursivelyIncludedData = $this->evalIncludes($includePath);

                        // Replace include directive in the actual template data.
                        $compiledData = str_replace($matches[0][$i], $recursivelyIncludedData, $compiledData);
                    }
                }
            }
        }

        // And, of course, return compiled data.
        return $compiledData;
    }

    /**
     * This method is used to substitute given variables in the compiled template.
     * If the variable exists in $viewVariables it gets substituted with the corresponding
     * value, else the default value is taken.
     */
    private function evalVariables()
    {
        /*
         * TODO: This expression is not taking into account default values with special chars in it, only A-Za-z0-9_
         *       Make this work even with those characters.
         */
        if (preg_match_all("/##\s(e?@(?:\w|\s)+\|?(?:\w|\s)+)##/", $this->compiledView, $matches))
        {
            for ($i = 0; $i < count($matches[0]); $i++)
            {
                $escaped = false;

                /*
                 * In order to make this work for variables with and without a default value I need to limit the size
                 * of the array returned by explode() to 2 values and, if there are less than 2 values returned, append
                 * an empty string with array_pad().
                 */
                list($variableName, $defaultValue) = array_pad(explode('|', $matches[1][$i], 2), 2, '');

                // Check if variable needs to be encoded.
                if (startsWith($variableName, 'e'))
                {
                    $variableName = ltrim($variableName, 'e');
                    $escaped = true;
                }

                // I also need to remove unnecessary spaces.
                $variableName = trim(ltrim($variableName, '@'));
                $defaultValue = trim($defaultValue);

                if (isset($this->viewVariables[$variableName]))
                {
                    $this->compiledView = str_replace($matches[0][$i], ($escaped == true) ? ent($this->viewVariables[$variableName]) : $this->viewVariables[$variableName], $this->compiledView);
                }
                else
                {
                    $this->compiledView = str_replace($matches[0][$i], $defaultValue, $this->compiledView);
                }
            }
        }
    }

    /**
     * This method is used to compile the final template to serve to the user.
     */
    private function buildView()
    {
        $this->compiledView = $this->evalIncludes($this->viewFile);
    }

    /**
     * This method is used to render a given view, or template.
     * It will automatically take care of including partials and setting appropriate value
     * of the variables.
     *
     * @param string $name     The view file to render.
     * @param array $variables The variables to set, if any.
     * @throws AliceException  If the view doesn't exists.
     */
    public function renderView($name, $variables = array())
    {
        $this->viewFile = Application::getPath('path.views') . DIRECTORY_SEPARATOR . "$name.php";
        $this->viewVariables = $variables;

        if (file_exists($this->viewFile))
        {
            $this->buildView();

            $this->evalVariables();

            echo $this->compiledView;
        }
        else
        {
            throw new AliceException($GLOBALS['VIEW_NOT_FOUND_MESSAGE'], $GLOBALS['VIEW_NOT_FOUND_CODE']);
        }
    }
}
