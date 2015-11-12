<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ZEN\TranslationBundle\Controller;

use ZEN\TranslationBundle\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Response;

use ZEN\TranslationBundle\Translation\XliffMessageUpdater;

use ZEN\TranslationBundle\Util\FileUtils;

use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/api")
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ApiController
{
    /** @DI\Inject("zen_translation.config_factory") */
    private $configFactory;

    /** @DI\Inject */
    private $request;

    /** @DI\Inject("zen_translation.updater") */
    private $updater;

    /**
     * @Route("/configs/{config}/domains/{domain}/locales/{locale}/messages",
     * 			name="zen_translation_update_message",
     * 			defaults = {"id" = null},
     * 			options = {"i18n" = false})
     * @Method("PUT")
     */
    public function updateMessageAction(Request $request, $config, $domain, $locale)
    {
        $id = $request->query->get('id');

        $config = $this->configFactory->getConfig($config, $locale);

        $files = FileUtils::findTranslationFiles($config->getTranslationsDir());
        if (!isset($files[$domain][$locale])) {
            throw new RuntimeException(sprintf('There is no translation file for domain "%s" and locale "%s".', $domain, $locale));
        }

        // TODO: This needs more refactoring, the only sane way I see right now is to replace
        //       the loaders of the translation component as these currently simply discard
        //       the extra information that is contained in these files

        list($format, $file) = $files[$domain][$locale];

        $this->updater->updateTranslation(
            $file, $format, $domain, $locale, $id,
            $this->request->request->get('message')
        );

        return new Response();
    }
    /**
     * @Route("/delete/configs/{config}/domains/{domain}/locales/{locale}/messages",
     * 			name="zen_translation_delete_message",
     * 			defaults = {"id" = null},
     * 			options = {"i18n" = false})
     * @Method("PUT")
     */
    public function deleteMessageAction(Request $request, $config, $domain, $locale)
    {
        $id = $request->query->get('id');

        $config = $this->configFactory->getConfig($config, $locale);

        $files = FileUtils::findTranslationFiles($config->getTranslationsDir());
        if (!isset($files[$domain][$locale])) {
            throw new RuntimeException(sprintf('There is no translation file for domain "%s" and locale "%s".', $domain, $locale));
        }

        // TODO: This needs more refactoring, the only sane way I see right now is to replace
        //       the loaders of the translation component as these currently simply discard
        //       the extra information that is contained in these files

        list($format, $file) = $files[$domain][$locale];

        $this->updater->delTranslation(
            $file, $format, $domain, $locale, $id,
            $this->request->request->get('message')
        );

        return new Response();
    }

    /**
     * @Route("/add/configs/{config}/domains/{domain}/locales/{locale}/messages",
     * 			name="zen_translation_add_message",
     * 			defaults = {"id" = null},
     * 			options = {"i18n" = false})
     * @Method("PUT")
     */
    public function addMessageAction(Request $request, $config, $domain, $locale)
    {
        $id = $request->query->get('id');

        $config = $this->configFactory->getConfig($config, $locale);

        $files = FileUtils::findTranslationFiles($config->getTranslationsDir());
        if (!isset($files[$domain][$locale])) {
            throw new RuntimeException(sprintf('There is no translation file for domain "%s" and locale "%s".', $domain, $locale));
        }

        // TODO: This needs more refactoring, the only sane way I see right now is to replace
        //       the loaders of the translation component as these currently simply discard
        //       the extra information that is contained in these files

        list($format, $file) = $files[$domain][$locale];

        $this->updater->addTranslation(
            $file, $format, $domain, $locale, $id,
            $this->request->request->get('message')
        );

        return new Response();
    }
}
