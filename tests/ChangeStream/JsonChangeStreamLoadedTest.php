<?php

/*
 * This file is part of the puli/repository package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Repository\Tests\ChangeStream;

use Puli\Repository\Api\ChangeStream\ChangeStream;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonChangeStreamLoadedTest extends JsonChangeStreamTest
{
    protected function createReadStream(ChangeStream $writeStream)
    {
        return $writeStream;
    }
}
