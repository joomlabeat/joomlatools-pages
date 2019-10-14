<?php
/**
 * Joomlatools Pages
 *
 * @copyright   Copyright (C) 2018 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-pages for the canonical source repository
 */

class ComPagesDispatcherRouterResolverPage extends ComPagesDispatcherRouterResolverRegex
{
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'routes' => $this->getObject('page.registry')->getRoutes(),
        ));

        parent::_initialize($config);
    }

    public function resolve(ComPagesDispatcherRouterRouteInterface $route)
    {
        if($result = parent::resolve($route))
        {
            $state = $route->getState();

            if(isset($route->query['page']))
            {
                $page  = $route->query['page'];

                if(isset($page['number']) && $state['limit']) {
                    $route->query['offset'] = ($page['number'] - 1) * $state['limit'];
                }

                if(isset($page['limit'])) {
                    $route->query['limit'] = $page['limit'];
                }

                if(isset($page['offset'])) {
                    $route->query['offset'] = $page['offset'];
                }

                if(isset($page['total'])) {
                    $route->query['total'] = $page['total'];
                }

                unset($route->query['page']);
            }
        }

        return $result;
    }

    public function generate(ComPagesDispatcherRouterRouteInterface $route)
    {
        if($result = parent::generate($route))
        {
            $state = $route->getState();
            $page = array();

            if(isset($route->query['offset']))
            {
                $page['offset'] = $route->query['offset'];
                unset($route->query['offset']);
            }

            if(isset($route->query['limit']))
            {
                $page['limit'] = $route->query['limit'];
                unset($route->query['offset']);
            }

            if(isset($route->query['total']))
            {
                $page['total'] = $route->query['total'];
                unset($route->query['total']);
            }

            if(isset($state['limit']) && isset($page['offset']))
            {
                $page['number'] = ceil($page['offset']/$state['limit']) + 1;
                unset($page['offset']);
            }

            $route->query['page'] = $page;
        }

        return $result;
    }
}