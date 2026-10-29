<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AskAiPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public const SECTION = 'sylius_ask_ai';

    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $section = $menu
            ->addChild(self::SECTION)
            ->setLabel('sylius_ask_ai.ui.ask_ai')
            ->setLabelAttribute('icon', 'tabler:message-chatbot')
        ;
        $section
            ->addChild('conversations', ['route' => 'sylius_ask_ai_admin_conversation_index', 'extras' => ['routes' => [
                ['route' => 'sylius_ask_ai_admin_conversation_show'],
            ]]])
            ->setLabel('sylius_ask_ai.ui.conversations')
            ->setLabelAttribute('icon', 'tabler:messages')
        ;
        $section
            ->addChild('agent_actions', ['route' => 'sylius_ask_ai_admin_agent_action_index', 'extras' => ['routes' => [
                ['route' => 'sylius_ask_ai_admin_agent_action_show'],
            ]]])
            ->setLabel('sylius_ask_ai.ui.agent_actions')
            ->setLabelAttribute('icon', 'tabler:checklist')
        ;

        $order = array_keys($menu->getChildren());
        $configuration = array_search('configuration', $order, true);
        if (false !== $configuration) {
            $order = array_values(array_diff($order, [self::SECTION]));
            array_splice($order, (int) array_search('configuration', $order, true), 0, [self::SECTION]);
            $menu->reorderChildren($order);
        }
    }
}
