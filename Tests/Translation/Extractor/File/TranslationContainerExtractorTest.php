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

namespace ZEN\TranslationBundle\Tests\Translation\Extractor\File;

use ZEN\TranslationBundle\Exception\RuntimeException;
use ZEN\TranslationBundle\Model\FileSource;
use ZEN\TranslationBundle\Model\Message;
use ZEN\TranslationBundle\Translation\Extractor\File\TranslationContainerExtractor;
use ZEN\TranslationBundle\Model\MessageCatalogue;

class TranslationContainerExtractorTest extends \PHPUnit_Framework_TestCase
{
    public function testExtractFormModel()
    {
        $expected = new MessageCatalogue();
        $path = __DIR__.'/Fixture/MyFormModel.php';

        $message = new Message('form.label.choice.foo');
        $message->addSource(new FileSource($path, 13));
        $expected->add($message);

        $message = new Message('form.label.choice.bar');
        $message->addSource(new FileSource($path, 13));
        $expected->add($message);

        $this->assertEquals($expected, $this->extract('MyFormModel.php'));
    }

    private function extract($file, TranslationContainerExtractor $extractor = null)
    {
        if (!is_file($file = __DIR__.'/Fixture/'.$file)) {
            throw new RuntimeException(sprintf('The file "%s" does not exist.', $file));
        }
        $file = new \SplFileInfo($file);

        if (null === $extractor) {
            $extractor = new TranslationContainerExtractor();
        }

        $lexer = new \PHPParser_Lexer();
        $parser = new \PHPParser_Parser($lexer);
        $ast = $parser->parse(file_get_contents($file));

        $catalogue = new MessageCatalogue();
        $extractor->visitPhpFile($file, $catalogue, $ast);

        return $catalogue;
    }
}
