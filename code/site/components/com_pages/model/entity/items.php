<?php
/**
 * Joomlatools Pages
 *
 * @copyright   Copyright (C) 2018 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-pages for the canonical source repository
 */

class ComPagesModelEntityItems extends KModelEntityComposite implements JsonSerializable, ComPagesModelEntityInterface
{
    use ComPagesObjectDebuggable;

    public function jsonSerialize()
    {
        $result = array();
        foreach ($this as $key => $entity) {
            $result[$key] = $entity->jsonSerialize();
        }

        return $result;
    }
}