<?php
/**
 * Joomlatools Pages
 *
 * @copyright   Copyright (C) 2018 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-pages for the canonical source repository
 */

class ComPagesViewHtml extends ComKoowaViewHtml
{
    use ComPagesViewTraitModellable, ComPagesViewTraitLocatable;

    protected function _initialize(KObjectConfig $config)
    {
        $config->append([
            'behaviors'   => ['layoutable'],
            'auto_fetch'  => false,
            'template_filters'   => ['asset', 'meta'],
            'template_functions' => [
                'page'        => [$this, 'getPage'],
                'collection'  => [$this, 'getCollection'],
                'state'       => [$this, 'getState'],
                'direction'   => [$this, 'getDirection'],
                'language'    => [$this, 'getLanguage'],
            ],
        ]);

        parent::_initialize($config);
    }

    protected function _actionRender(KViewContext $context)
    {
        $content = $this->getContent();

        return trim($content);
    }

    protected function _fetchData(KViewContext $context)
    {
        parent::_fetchData($context);

        if($this->isCollection()) {
            $context->parameters->total = $this->getModel()->count();
        }
    }
}