<?php
// Copyright (c) 2016 Glauber Portella <glauberportella@gmail.com>

// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the "Software"),
// to deal in the Software without restriction, including without limitation
// the rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following conditions:

// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
// FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
// DEALINGS IN THE SOFTWARE.

namespace CnabParser;

use CnabParser\Format\Picture;
use CnabParser\Model\Linha;
use CnabParser\Model\Retorno;
use CnabParser\Parser\Layout;
use CnabParser\IntercambioBancarioFileAbstract;

abstract class IntercambioBancarioGeraRetornoFileAbstract extends IntercambioBancarioFileAbstract
{
    // Para CNAB240
    const REGISTRO_HEADER_ARQUIVO = 0;
    const REGISTRO_HEADER_LOTE = 1;
    const REGISTRO_DETALHES = 3;
    const REGISTRO_TRAILER_LOTE = 5;
    const REGISTRO_TRAILER_ARQUIVO = 9;

    // Para CNAB400

    /**
     * @var CnabParser\Parser\Layout
     */
    protected $layout;

    protected $path;

    protected $linhas;

    /**
     * Total de lotes em um arquivo
     * @var integer
     */
    protected $totalLotes;

    public function __construct(IntercambioBancarioAbstract $model)
    {
        $this->model = $model;
    }

    public function getTotalLotes()
    {
        return $this->totalLotes;
    }

    protected function calculaTotalLotes()
    {
        $this->totalLotes = 1;

        $layout = $this->layout->getLayout();

        $linhaTrailerArquivoStr = $this->linhas[count($this->linhas) - 1];
        $linha = new Linha($linhaTrailerArquivoStr, $this->layout, 'retorno');

        if (strtoupper($layout) === strtoupper('cnab240')) {
            // conforme cnab240 febraban
            $definicao = array(
                'pos'     => array(18, 23),
                'picture' => '9(6)',
            );
            $this->totalLotes = (int) $linha->obterValorCampo($definicao);
        } elseif (strtoupper($layout) === strtoupper('cnab400')) {
            $this->totalLotes = 1; // cnab400 apenas 1 lote
        }

        return $this->totalLotes;
    }

    protected function encode($fieldsDef, $modelSection)
    {
        $encoded = '';
        foreach ($fieldsDef as $field => $definition) {
            if (isset($modelSection->$field)) {
                $format = $definition['picture'];
                $encoded .= Picture::encode($modelSection->$field, $format, array('field_desc' => $field));
            }
        }
        return $encoded;
    }
}