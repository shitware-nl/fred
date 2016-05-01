<?php

namespace Rsi\Fred;

class Pdf extends Component{

  const ORIENTATION_PORTRAIT = 'portrait';
  const ORIENTATION_LANDSCAPE = 'landscape';

  const PAPER_A0 = 'a0';
  const PAPER_A1 = 'a1';
  const PAPER_A2 = 'a2';
  const PAPER_A3 = 'a3';
  const PAPER_A4 = 'a4';
  const PAPER_A5 = 'a5';
  const PAPER_LETTER = 'letter';
  const PAPER_LEGAL = 'legal';

  public $defaultBackEnd = 'domPdf';
  public $paper = self::PAPER_A4; //!<  Default paper size.
  public $orientation = self::ORIENTATION_PORTRAIT; //!<  Default paper orientation.

  public $webKitHtmlToPdfPath = null; //!<  wkHTMLtoPDF command-line, with placeholders voor [options] [source] en [target].

  protected $_backEnds = null;
  protected $_domPdf = null;

  /**
   *  Create a PDF file using DomPdf.
   *  @see create()
   */
  public function createDomPdf($html,$filename = null,$paper = null,$orientation = null,$options = null){
    $this->domPdf->load_html($html);
    $this->domPdf->set_paper($paper ?: $this->paper,$orientation ?: $this->orientation);
    $this->domPdf->render();
    $data = $this->domPdf->output();
    return $filename ? file_put_contents($filename,$data) : $data;
  }
  /**
   *  Create a PDF file using WebKit.
   *  @see create()
   */
  public function createWebKit($html,$filename = null,$paper = null,$orientation = null,$options = null){
    \Rsi\File::unlink($target = $filename ?: \Rsi\File::tempFile('pdf'));
    shell_exec(strtr($this->webKitHtmlToPdfPath,[
      '[options]' => '--' . \Rsi\Record\implode(array_merge(['page-size' => $paper,'orientation' => $orientation],$options ?: []),' --',' '),
      '[source]' => $source = \Rsi\File::tempFile('pdf',$html),
      '[target]' => $target
    ]));
    if(!file_exists($target)) return false;
    if($filename) return filesize($filename);
    $data = file_get_contents($target);
    unlink($target);
    return $data;
  }
  /**
   *  Create a PDF file using the default back-end.
   *  @param string $html  HTML presentation of content.
   *  @param string $filename  Target file (empty = return data).
   *  @param string $paper  Paper size (see PAPER_* constants; empty = default).
   *  @param string $orientation  Paper orientation (see ORIENTATION_* constants; empty = default).
   *  @param array $options  Extra options (back-end specific).
   *  @return mixed  Data if no filename given, otherwise filesize (false on error).
   */
  public function create($html,$filename = null,$paper = null,$orientation = null,$options = null){
    return call_user_func([$this,'create' . ucfirst($this->defaultBackEnd)],$html,$filename,$paper,$orientation,$options);
  }

  protected function getBackEnds(){
    if($this->_backEnds === null){
      $this->_backEnds = [];
      foreach(get_class_methods($this) as $method) if((strlen($method) > 6) && (substr($method,0,6) == 'create'))
        $this->_backEnds[] = lcfirst(substr($method,6));
    }
    return $this->_backEnds;
  }

  protected function getDomPdf(){
    if(!$this->_domPdf){
      $reflect = new \ReflectionClass('DOMPDF_Exception');
      require_once(dirname(dirname($reflect->getFileName())) . '/dompdf_config.inc.php');
      $this->_domPdf = new \DOMPDF();
    }
    return $this->_domPdf;
  }

}