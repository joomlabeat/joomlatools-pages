<?php
/**
 * Joomlatools Pages
 *
 * @copyright   Copyright (C) 2018 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-pages for the canonical source repository
 */

class ComPagesPageObject extends ComPagesObjectConfigFrontmatter
{
    public function getType()
    {
        $type = 'page';

        if($this->isCollection()) {
            $type = 'collection';
        }

        return $type;
    }
    
    public function isCollection()
    {
        return isset($this->collection) && $this->collection !== false ? KObjectConfig::unbox($this->collection) : false;
    }
}