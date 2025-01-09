<?php

/*
 * This file is part of the DataGridBundle.
 *
 * (c) Abhoryo <abhoryo@free.fr>
 * (c) Stanislav Turza
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace APY\DataGridBundle\Grid\Export;

use APY\DataGridBundle\Grid\Grid;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * XML.
 */
class XMLExport extends Export
{
    protected ?string $fileExtension = 'xml';

    protected string $mimeType = 'application/xml';

    public function computeData(Grid $grid): void
    {
        $xmlEncoder = new XmlEncoder();
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['xml' => $xmlEncoder]);

        $data = $this->getGridData($grid);

        $convertData['titles']      = $data['titles'];
        $convertData['rows']['row'] = $data['rows'];

        $this->content = $serializer->serialize($convertData, 'xml');
    }
}
