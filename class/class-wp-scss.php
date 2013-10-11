<?php 

class Wp_Scss {
  /**
   * Compiling preferences properites
   * 
   * @var string
   * @access public
   */
  public $scss_dir, $css_dir, $compile_method, $scssc, $compile_errors;
   

  /**
   * Set values for Wp_Scss::properties
   * 
   * @param string scss_dir - path to source directory for scss files
   * @param string css_dir - path to output directory for css files
   * @param string method - type of compile (compressed, expanded, etc)
   * 
   * @var object scssc - instantiate the compiling object.
   *
   * @var array compile_errors - catches errors from compile
   */
  public function __construct ($scss_dir, $css_dir, $compile_method) {
    $this->scss_dir = $scss_dir;
    $this->css_dir = $css_dir;
    $this->compile_method = $compile_method;

    global $scssc;
    $scssc = new scssc();
    $scssc->setFormatter($compile_method);
    $scssc->setImportPaths($scss_dir); 

    $this->compile_errors = array();
  }

 /** 
   * METHOD COMPILE
   * Loops through scss directory and compilers files that end
   * with .scss and do not have '_' in front.
   *
   * @function compiler - passes input content through scssphp, 
   *                      puts compiled css into cache file
   *
   * @var array input_files - array of .scss files with no '_' in front
   * @var array sdir_arr - an array of all the files in the scss directory
   * 
   * @return nothing - Puts successfully compiled css into apporpriate location 
   *                   Puts error in 'compile_errors' property
   * @access public
   */
  public function compile() {
      global $scssc, $cache;
      $cache = WPSCSS_PLUGIN_DIR . '/cache/';
      
      //Compiler - Takes scss $in and writes compiled css to $out file
      // catches errors and puts them the object's compiled_errors property
      function compiler($in, $out, $instance) {  
        global $scssc, $cache;  
        
        try {
            $css = $scssc->compile(file_get_contents($in));
            file_put_contents($cache.basename($out), $css);
        } catch (Exception $e) {
            $errors = array (
              'file' => basename($in),
              'message' => $e->getMessage(),
              );
            array_push($instance->compile_errors, $errors);
        }
      }

      $input_files = array();      
      // Loop through directory and get .scss file that do not start with '_'
      foreach(new DirectoryIterator($this->scss_dir) as $file) {
        if (substr($file, 0, 1) != "_" && $file->getExtension() == 'scss') {
          array_push($input_files, $file->getFilename());
        }
      }
      
      // For each input file, find matching css file and compile
      foreach ($input_files as $scss_file) {
        $input = $this->scss_dir.$scss_file;
        $outputName = preg_replace("/\.[^$]*/",".css", $scss_file);
        $output = $this->css_dir.$outputName;
        
        compiler($input, $output, $this);
      }

      if (count($this->compile_errors) < 1) {
        foreach (new DirectoryIterator($cache) as $cache_file) {
          if ( $cache_file->isDot() ) continue; 
          file_put_contents($this->css_dir.$cache_file, file_get_contents($cache.$cache_file));
        }
      }
  } 


  /** 
   * METHOD NEEDS_COMPILING
   * Gets the most recently modified file in the scss directory
   * and compares that do the most recently modified css file.
   * If scss is greater, we assume that changes have been made
   * and compiling needs to occur to update css.
   *
   * @param string scss_dir - path to scss folder
   * @param string css_dir - path to css folder
   *
   * @var array sdir_arr - scss directory files
   * @var array cdir_arr - css directory files
   * 
   * @var string latest_scss - file mod time of the most recent file change
   * @var string latest_css - file mod time of the most recent file change
   * 
   * @return bool - true if compiling is needed
   */
    public function needs_compiling() {
      $sdir_arr = scandir($this->scss_dir);
      $cdir_arr = scandir($this->css_dir);
      $latest_scss = 0;
      $latest_css = 0;

      foreach ( new DirectoryIterator($this->scss_dir) as $sfile ) {
        if ($sfile->getExtension() == 'scss') {
          $file_time = $sfile->getMTime();

          if ( (int) $file_time > $latest_scss) {
            $latest_scss = $file_time; 
          }
        }
      }

      foreach ( new DirectoryIterator($this->css_dir) as $cfile ) {
        if ($cfile->getExtension() == 'css') {
          $file_time = $cfile->getMTime();
        
          if ( (int) $file_time > $latest_css) {
            $latest_css = $file_time; 
          }
        }
      }
      
      if ($latest_scss > $latest_css) {
        return true;
      } else {
        return false; 
      }
    }

  /** 
   * METHOD ENQUEUE STYLES
   * Enqueues all styles in the css directory.
   *
   */
  public function enqueue_files($css_folder) {
      
      foreach( new DirectoryIterator($this->css_dir) as $stylesheet ) {
        if ( $stylesheet->getExtension() == 'css' ) {
          $name = $stylesheet->getBasename('.css') . '-style';
          $uri = get_stylesheet_directory_uri().$css_folder.$stylesheet->getFilename();
          $ver = $stylesheet->getMTime();

          
          wp_register_style( 
            $name,
            $uri,
            array(),
            $ver,
            $media = 'all' );

          wp_enqueue_style( $name );
        }
      }
  }

} // End Wp_Scss Class