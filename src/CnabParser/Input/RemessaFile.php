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

namespace CnabParser\Input;

use CnabParser\Model\Linha;
use CnabParser\Model\Retorno;
use CnabParser\Parser\Layout;
use CnabParser\Exception\RetornoException;
use CnabParser\IntercambioBancarioRemessaFileAbstract;

class RemessaFile extends IntercambioBancarioRemessaFileAbstract
{
    /**
     * [__construct description]
     * @param Layout $layout Layout do remessa
     * @param string $path   Caminho do arquivo de remessa a ser processado
     */
    public function __construct(Layout $layout, $path)
    {
        $this->layout = $layout;
        $this->path = $path;

        $this->linhas = file($this->path, FILE_IGNORE_NEW_LINES);
        if (false === $this->linhas) {
            throw new RetornoException('Falha ao ler linhas do arquivo de remessa "' . $this->path . '".');
        }

        $this->totalLotes = 1;

        $this->model = new Retorno();
    }

    /**
     * Para remessa o metodo em questao gera o modelo Remessa conforme layout
     * @param  string $path Não necessario
     * @return CnabParser\Model\Remessa
     */
    public function generate($path = null)
    {
        $this->decodeHeaderArquivo();
        $this->decodeTrailerArquivo();
        $this->decodeLotes();
        return $this->model;
    }

    /**
     * Processa header_arquivo
     */
    protected function decodeHeaderArquivo()
    {
        $layout = $this->layout->getRemessaLayout();
        $headerArquivoDef = $layout['header_arquivo'];
        $linha = new Linha($this->linhas[0], $this->layout, 'remessa');
        foreach ($headerArquivoDef as $campo => $definicao) {
            $valor = $linha->obterValorCampo($definicao);
            $this->model->header_arquivo->{$campo} = $valor;
        }
    }

    /**
     * Processa trailer_arquivo
     */
    protected function decodeTrailerArquivo()
    {
        $layout = $this->layout->getRemessaLayout();
        $trailerArquivoDef = $layout['trailer_arquivo'];
        $linha = new Linha($this->linhas[count($this->linhas) - 1], $this->layout, 'remessa');
        foreach ($trailerArquivoDef as $campo => $definicao) {
            $valor = $linha->obterValorCampo($definicao);
            $this->model->trailer_arquivo->{$campo} = $valor;
        }
    }

    protected function decodeLotes()
    {
        $tipoLayout = $this->layout->getLayout();

        if (strtoupper($tipoLayout) === strtoupper('cnab200')) {
            $this->decodeLoteCnab200();
        }

        if (strtoupper($tipoLayout) === strtoupper('cnab400')) {
            $this->decodeLotesCnab400();
        }
    }

    private function decodeLoteCnab200()
    {
        $defTipoRegistro = array(
            'pos'     => array(1, 2),
            'picture' => 'X(2)',
        );

        $defCodigoSegmento = array(
            'pos'     => array(1, 2),
            'picture' => 'X(2)',
        );

        $primeiroCodigoSegmentoLayout = $this->layout->getPrimeiroCodigoSegmentoRemessa();
        $ultimoCodigoSegmentoLayout = $this->layout->getUltimoCodigoSegmentoRemessa();

        $lote = null;
        $titulos = array(); // titulos tem titulo
        $segmentos = array();
        foreach ($this->linhas as $index => $linhaStr) {
            $linha = new Linha($linhaStr, $this->layout, 'remessa');
            $tipoRegistro = (int) $linha->obterValorCampo($defTipoRegistro);

            if ($tipoRegistro === IntercambioBancarioRemessaFileAbstract::REGISTRO_HEADER_ARQUIVO) {
                continue;
            }

            if ($tipoRegistro === IntercambioBancarioRemessaFileAbstract::REGISTRO_TRAILER_ARQUIVO) {
                $lote['titulos'][] = $segmentos;
                $segmentos = array();
                break;
            }

            // estamos tratando detalhes
            $codigoSegmento = $linha->obterValorCampo($defCodigoSegmento);
            $dadosSegmento = $linha->getDadosSegmento('segmento_' . strtolower($codigoSegmento));
            $segmentos[$codigoSegmento] = $dadosSegmento;
            $proximaLinha = new Linha($this->linhas[$index + 1], $this->layout, 'remessa');
            $proximoCodigoSegmento = $proximaLinha->obterValorCampo($defCodigoSegmento);
            // se (
            //     proximo codigoSegmento é o primeiro OU
            //     codigoSegmento é ultimo
            // )
            // entao fecha o titulo e adiciona em $detalhes
            if (
                strtolower($proximoCodigoSegmento) === strtolower($primeiroCodigoSegmentoLayout) ||
                strtolower($codigoSegmento) === strtolower($ultimoCodigoSegmentoLayout)
            ) {
                $lote['titulos'][] = $segmentos;
                // novo titulo, novos segmentos
                $segmentos = array();
            }
        }

        $this->model->lotes[] = $lote;
    }

    private function decodeLotesCnab400()
    {
        $defTipoRegistro = array(
            'pos'     => array(1, 1),
            'picture' => '9(1)',
        );

        // para Cnab400 codigo do segmento na configuracao yaml é o codigo do registro
        $defCodigoSegmento = array(
            'pos'     => array(1, 1),
            'picture' => '9(1)',
        );

        $defNumeroRegistro = array(
            'pos'     => array(395, 400),
            'picture' => '9(6)',
        );

        $codigoLote = null;
        $primeiroCodigoSegmentoLayout = $this->layout->getPrimeiroCodigoSegmentoRemessa();
        $ultimoCodigoSegmentoLayout = $this->layout->getUltimoCodigoSegmentoRemessa();

        $lote = null;
        $segmentos = array();
        foreach ($this->linhas as $index => $linhaStr) {
            $linha = new Linha($linhaStr, $this->layout, 'remessa');
            $tipoRegistro = (int) $linha->obterValorCampo($defTipoRegistro);

            if ($tipoRegistro === IntercambioBancarioRemessaFileAbstract::REGISTRO_HEADER_ARQUIVO_400) {
                continue;
            }

            if ($tipoRegistro === IntercambioBancarioRemessaFileAbstract::REGISTRO_TRAILER_ARQUIVO_400) {
                $lote['titulos'][] = $segmentos;
                $segmentos = array();
                break;
            }

            // estamos tratando detalhes
            $codigoSegmento = $linha->obterValorCampo($defCodigoSegmento);
            $numeroRegistro = $linha->obterValorCampo($defNumeroRegistro);
            $dadosSegmento = $linha->getDadosSegmento('segmento_' . strtolower($codigoSegmento));
            $segmentos[$codigoSegmento] = $dadosSegmento;
            $proximaLinha = new Linha($this->linhas[$index + 1], $this->layout, 'remessa');
            $proximoCodigoSegmento = $proximaLinha->obterValorCampo($defCodigoSegmento);
            // se (
            //     proximo codigoSegmento é o primeiro OU
            //     codigoSegmento é ultimo
            // )
            // entao fecha o titulo e adiciona em $detalhes
            if (
                strtolower($proximoCodigoSegmento) === strtolower($primeiroCodigoSegmentoLayout) ||
                strtolower($codigoSegmento) === strtolower($ultimoCodigoSegmentoLayout)
            ) {
                $lote['titulos'][] = $segmentos;
                // novo titulo, novos segmentos
                $segmentos = array();
            }
        }

        $this->model->lotes[] = $lote;
    }
}
