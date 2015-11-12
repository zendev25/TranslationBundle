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
use ZEN\TranslationBundle\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ZEN\TranslationBundle\Util\FileUtils;
use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Translate Controller.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TranslateController extends Controller {

    /** @DI\Inject */
    private $request;

    /** @DI\Inject("zen_translation.config_factory") */
    private $configFactory;

    /** @DI\Inject("zen_translation.loader_manager") */
    private $loader;

    /** @DI\Inject("service_container") */
    protected $container;

    /** @DI\Inject("%zen_translation.source_language%") */
    private $sourceLanguage;

    /**
     * @Route("/", name="zen_translation_index", options = {"i18n" = false})
     * @Template
     * @param string $config
     */
    public function indexAction() {
        $configs = $this->configFactory->getNames();
        $config = $this->request->query->get('config') ? : reset($configs);
        if (!$config) {
            throw new RuntimeException('You need to configure at least one config under "zen_translation.configs".');
        }

        $translationsDir = $this->configFactory->getConfig($config, 'en')->getTranslationsDir();
        
        $files = FileUtils::findTranslationFiles($translationsDir);

        if (empty($files)) {
            throw new RuntimeException('There are no translation files for this config, please run the translation:extract command first.');
        }
        $domains = array_keys($files);

        $getDomain = $this->request->query->get('domain') ? : reset($domains);
        if ($getDomain != "all") {
            if (((!$domain = $this->request->query->get('domain')) || !isset($files[$domain]))) {
                $domain = reset($domains);
            }

            $locales = array_keys($files[$domain]);

            natsort($locales);

            if ((!$locale = $this->request->query->get('locale')) || !isset($files[$domain][$locale])) {
                $locale = reset($locales);
            }

            $catalogue = $this->loader->loadFile(
                    $files[$domain][$locale][1]->getPathName(), $files[$domain][$locale][0], $locale, $domain
            );

            // create alternative messages
            // TODO: We should probably also add these to the XLIFF file for external translators,
            //       and the specification already supports it

            $alternativeMessages = array();
            foreach ($locales as $otherLocale) {
                if ($locale === $otherLocale) {
                    continue;
                }

                $altCatalogue = $this->loader->loadFile(
                        $files[$domain][$otherLocale][1]->getPathName(), $files[$domain][$otherLocale][0], $otherLocale, $domain
                );
                foreach ($altCatalogue->getDomain($domain)->all() as $id => $message) {
                    $alternativeMessages[$id][$otherLocale] = $message;
                }
            }

            $newMessages = $existingMessages = array();
            foreach ($catalogue->getDomain($domain)->all() as $id => $message) {
                if ($message->isNew()) {
                    $newMessages[$id] = $message;
                    continue;
                }

                $existingMessages[$id] = $message;
            }
        } else {
            $locale = $this->request->query->get('locale');

            foreach ($domains as $domain) {
                $catalogue[] = $this->loader->loadFile(
                        $files[$domain][$locale][1]->getPathName(), $files[$domain][$locale][0], $locale, $domain
                );
            }

            $locales = array_keys($files[$domain]);

            natsort($locales);

            $alternativeMessages = array();

            $altCatalogue = $this->loader->loadFile(
                    $files[$domain][$locale][1]->getPathName(), $files[$domain][$locale][0], $locale, $domain
            );
            foreach ($altCatalogue->getDomain($domain)->all() as $id => $message) {
                $alternativeMessages[$id][$locale] = $message;
            }

            $newMessages = $existingMessages = array();

            for ($i = 0; $i < count($catalogue); $i++) {
                foreach ($catalogue[$i]->getDomain($domains[$i])->all() as $id => $message) {
                    if ($message->isNew()) {
                        $newMessages[$id] = $message;
                        continue;
                    }

                    $existingMessages[$id] = $message;
                }
            }
        }


        return array(
            'selectedConfig' => $config,
            'configs' => $configs,
            'getDomain' => $getDomain,
            'selectedDomain' => $domain,
            'domains' => $domains,
            'selectedLocale' => $locale,
            'locales' => $locales,
            'format' => $files[$domain][$locale][0],
            'newMessages' => $newMessages,
            'existingMessages' => $existingMessages,
            'alternativeMessages' => $alternativeMessages,
            'isWriteable' => is_writeable($files[$domain][$locale][1]),
            'file' => (string) $files[$domain][$locale][1],
            'sourceLanguage' => $this->sourceLanguage,
        );
    }

    /**
     * @Route("/delete-domain", name="zen_translation_delete_domain", options = {"i18n" = false})
     */
    public function deleteDomainAction(Request $request) {
        $configs = $this->configFactory->getNames();
        $config = $this->request->query->get('config') ? : reset($configs);
        if (!$config) {
            throw new RuntimeException('You need to configure at least one config under "zen_translation.configs".');
        }
        $translationsDir = $this->configFactory->getConfig($config, 'en')->getTranslationsDir();
        $files = FileUtils::findTranslationFiles($translationsDir);

        if (empty($files)) {
            throw new RuntimeException('There are no translation files for this config, please run the translation:extract command first.');
        }
        $domains = array_keys($files);

        if (((!$domain = $this->request->query->get('domain')) || !isset($files[$domain]))) {
            $domain = reset($domains);
        }
        $locales = array_keys($files[$domain]);

        natsort($locales);

        if ((!$locale = $this->request->query->get('locale')) || !isset($files[$domain][$locale])) {
            $locale = reset($locales);
        }

        if ($request->getMethod() == "POST") {
            $domain = $request->request->get('domain');
            $config = $request->request->get('config');
            $locale = $request->request->get('locale');

            $translationsDir = $this->configFactory->getConfig($config, 'en')->getTranslationsDir();
            $files = FileUtils::findTranslationFiles($translationsDir);


            $fs = new Filesystem();
            $folderPath = $files[$domain][$locale][1]->getPathName();

            try {
                $fs->remove($folderPath);
            } catch (IOException $e) {
                echo "Impossible de supprimer le fichier (inexistant ?)";
            }

            $url = $this->get('router')->generate('zen_translation_delete_domain');
            return $this->redirect($url);
        }



        return $this->render('ZENTranslationBundle:Translate:delete.html.twig', array(
                    'selectedConfig' => $config,
                    'configs' => $configs,
                    'selectedDomain' => $domain,
                    'domains' => $domains,
                    'selectedLocale' => $locale,
                    'locales' => $locales,
                    'format' => $files[$domain][$locale][0],
                    'isWriteable' => is_writeable($files[$domain][$locale][1]),
                    'file' => (string) $files[$domain][$locale][1],
                    'sourceLanguage' => $this->sourceLanguage,
        ));
    }
    
    
    /**
     * @Route("/clear-cache", name="zen_translation_cache_clear", options = {"i18n" = false})
     */
    public function clearCacheAction(Request $request){
        
        $fs = new Filesystem();
        $fs->remove($this->container->getParameter('kernel.cache_dir'));
        
        die('Cache vidée, tu peux fermer la page');
        
        if ($request->isXmlHttpRequest()) {
            $response = new Response();
            $output = array('type' => 'success', 'content' => 'cacheClear.success');
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($output));
            return $response;
        } else {
            $url = $this->get('router')->generate('zen_translation_index');
            return new RedirectResponse($url);
        }
    }

}
