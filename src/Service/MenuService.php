<?php

declare(strict_types=1);

namespace App\Service;

use Survos\TablerBundle\Event\MenuEvent;
use Survos\TablerBundle\Menu\MenuBuilderTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class MenuService
{
    use MenuBuilderTrait;

    #[AsEventListener(event: MenuEvent::NAVBAR_MENU)]
    public function navigation(MenuEvent $event): void
    {
        $this->add($event->menu, 'app_homepage', label: 'Overview', icon: 'tabler:world-heart', translationDomain: 'routing');
        $this->add($event->menu, 'browse_projects', label: 'Projects', icon: 'tabler:search', translationDomain: 'routing');
        $this->add($event->menu, 'browse_organizations', label: 'Organizations', icon: 'tabler:building-community', translationDomain: 'routing');
        $this->add($event->menu, 'browse_lab', label: 'AI Lab', icon: 'tabler:brain', translationDomain: 'routing');
        $this->add($event->menu, uri: '/api', label: 'Data API', icon: 'tabler:code', translationDomain: 'routing');
    }
}
