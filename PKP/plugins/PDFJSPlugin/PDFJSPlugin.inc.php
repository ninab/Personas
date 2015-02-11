<?php 
import('classes.plugins.GenericPlugin'); 
import('lib.pkp.classes.db.DAO');

class PDFJSPlugin extends GenericPlugin { 

    function register($category, $path) { 
        if (parent::register($category, $path)) {    
			HookRegistry::register( 
				'Templates::Reviewer::Submission::inlineText',
				 // 'Templates::Manager::Index::ManagementPages', 
            	array(&$this, 'callback') 
        	); 
        	
            $this->addLocaleData();
            if ($this->getEnabled()) {
            	// Insert header on every header (callback function checks if it addition is really need)
            	HookRegistry::register('TemplateManager::display',array(&$this, 'PDFJSPlugin_TemplateCallback'));
            }
        	
            return true; 
        } 
        return false; 
    } 
    function getName() { 
        return 'PDFJSPlugin'; 
    } 
    function getDisplayName() { 
        return 'PDF.js plugin'; 
    } 
    function getDescription() { 
        return 'The PDF.js plugin prepends an attached document with the document presented using the PDF.js code to allow a document to be presented inline and expose it to annotation.'; 
    } 
    
    function PDFJSPlugin_TemplateCallback($hookName, $args) {
    	//First argument is a TemplateManager object.
        $templateMgr =& $args[0];

        //Getting some context.
        $journal = &Request::getJournal();
        $journalId = $journal->getJournalId();
        $page = Request::getRequestedPage();
        $op = Request::getRequestedOp();
        $currentJournal = $templateMgr->get_template_vars('currentJournal');

        //Add additionalHeader just in a few pages of the current Journal:
        if (!empty($currentJournal))
      {
         //Extending template's head:
            $additionalHeadData = $templateMgr->get_template_vars('additionalHeadData');
            $additionalHeadData .= "\n\n        <!-- PDFJSPlugin Headers -->\n";
            $additionalHeadData .= '<script type="text/javascript" src="' . Request::getBaseUrl() . '/plugins/generic/PDFJSPlugin/pdfjs/build/pdf.js"></script>' . "\n";
            $additionalHeadData .= '<script type="text/javascript" src="' . Request::getBaseUrl() . '/plugins/generic/PDFJSPlugin/pdfjs/build/pdf.worker.js"></script>' . "\n";
            $additionalHeadData .= "        <!-- PDFJSPlugin Headers -->";

            $templateMgr->assign('additionalHeadData', $additionalHeadData);
      }
      
        return false;
    }

    
    function callback($hookName, $args) {   
    	import('classes.submission.author.AuthorSubmissionDAO');
		import('classes.file.ArticleFileManager');
		import('classes.article.ArticleDAO');
		import('classes.submission.reviewAssignment.PKPReviewAssignment');
		    
        $params =& $args[0]; 
        $smarty =& $args[1]; 
        $output =& $args[2]; 
        
        $reviewId = array_pop(explode("/", $args[1]->request->_requestPath));
        
        # infer the SubmissionID (what we actually need) from the SubmissionId thay comes from the URL

		$PDFjsDAO = new PDFJSPluginDAO();
		$submissionId = $PDFjsDAO->getSubmissionId($reviewId);		

		# the directory

    	// print __LINE__." - <pre>".print_r($args[0], TRUE)."</pre>";
    	// print __LINE__." - ".print_r($args[1], TRUE);
    	// print __LINE__." - ".print_r($args[2], TRUE);

        $file_manager = new ArticleFileManager($submissionId); // 6); 
       	$dir = $file_manager->filesDir;       

		$DAO = new AuthorSubmissionDAO();
        $files = $DAO->getAuthorSubmission($submissionId); // 6); 
		 
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$articleFiles =& $articleFileDao->getArticleFilesByArticle($submissionId); // 6);

        $PKPReview = new PKPReviewAssignment();
        $PKPReview->setSubmissionId($submissionId);
            
		/*
		$file_test = "<pre>";
		foreach ($articleFiles as $articleFile) {
			$file_test .= print_r($articleFile, TRUE)."\n ------------- \n";
		}
		$file_test .= "</pre>";	            
        */
            
        $file = array();        
		foreach ($articleFiles as $articleFile) {
			if (($articleFile->_data['filetype'] == 'application/pdf') && (strpos($articleFile->_data['fileName'],'-RV') > 1)) {
				$aFile =& $file_manager->getFile($articleFile->_data['fileId'], $revision);
				$filePath = $file_manager->filesDir .  $file_manager->fileStageToPath($aFile->getFileStage()) . '/' . $aFile->getFileName(); 

				// $file[] = $dir."submission/".$subpath."/".$articleFile->_data['fileName'].$file_manager->downloadFile($articleFile->_data['fileId'], null, true)."\n ------------- \n";								
				$file[] = __LINE__." - ".$filePath."\n----- \n ".print_r($articleFile, TRUE)."\n ------------- \n";

				$path_elements = array_filter(explode('/', $_SERVER['PATH_INFO']));	
				$journal = array_shift(array_values($path_elements));				
				$reviewId = array_pop(array_values($path_elements));
				$new_path = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].str_replace($_SERVER['PATH_INFO'],"", $_SERVER['REQUEST_URI'])."/".$journal."/reviewer/downloadFile/".$reviewId.'/'.$articleFile->_data['submissionId'].'/'.$articleFile->_data['fileId'].'/'.$articleFile->_data['revision'];

				// work it into here		
		
			}
		}
		
		/*	
		$output = "<pre>".print_r($_SERVER, TRUE)."</pre>";			
		$output .= __LINE__.$_SERVER['DOCUMENT_ROOT']."<br/>";
		$output .= __LINE__.dirname(__FILE__)."<br/>";					
		*/

		$plugin_dir = str_replace($_SERVER['DOCUMENT_ROOT'],"",dirname(__FILE__));
		// $output .= $plugin_dir."<br/>";					

		// why is this not kicking out info?
		// $output .= '<iframe src="'.$plugin_dir.'/pdfjs/web/viewer.html?file='.urlencode($new_path).'" width="800" height="400"></iframe>';
		$output .= '<br/>';		
		$output .= '<a href="'.$plugin_dir.'/pdfjs/web/viewer.html?file='.urlencode($new_path).'" target="_new">Open to view and annotate</a>';
		$output .= '<br/>';		
		
		/*
		$output .= '<div id="pdfDownloadLinkContainer">';
		$output .= '<a class="action pdf" id="pdfDownloadLink" target="_parent" href="'.$new_path.'">Download</a>'."\n";
		$output .= '</div>'."\n";
		$output .= '<script type="text/javascript" src="'.$plugin_dir.'/pdfjs/build/pdf.js"></script>'."\n";
		$output .= '<script type="text/javascript">'."\n";
		$output .= '$(document).ready(function() {'."\n";
		$output .= "PDFJS.workerSrc='".$plugin_dir."/pdfjs/build/pdf.worker.js'"."\n";
		$output .= "PDFJS.getDocument('".$new_path."').then(function(pdf) {"."\n";
		$output .= "// Using promise to fetch the page"."\n";
		$output .= "pdf.getPage(1).then(function(page) {"."\n";
		$output .= "var scale = 1.5;"."\n";
		$output .= "var viewport = page.getViewport(scale);"."\n";
		$output .= "var canvas = document.getElementById('pdfCanvas');"."\n";
		$output .= "var context = canvas.getContext('2d');"."\n";
		$output .= "var pdfCanvasContainer = $('#pdfCanvasContainer');"."\n";
		$output .= "canvas.height = pdfCanvasContainer.height();"."\n";
		$output .= "canvas.width = pdfCanvasContainer.width()-2; // 1px border each side"."\n";
		$output .= "var renderContext = {"."\n";
		$output .= "canvasContext: context,"."\n";
		$output .= "viewport: viewport"."\n";
		$output .= "};"."\n";
		$output .= "page.render(renderContext);"."\n";
		$output .= "});"."\n";
		$output .= "});"."\n";
		$output .= "});"."\n";
		$output .= "</script>"."\n";
		$output .= '<div id="pdfCanvasContainer" style="min-height: 500px;">'."\n";
		$output .= '<canvas id="pdfCanvas" style="border:1px solid black;"/>'."\n";
		$output .= '</div>'."\n";
		*/				
		
		// $output .= "<ul><li>".implode("</li><li>", $file)."</li></ul>".$new_path;        
        return false; 
    } 
    
/**
* @see Plugin::manage()
*/
function manage($verb, $args, &$message, &$messageParams, &$pluginModalContent = null) {
	if (!parent::manage($verb, $args, $message, $messageParams)) return false;
		$request = $this->getRequest();
		switch ($verb) {
			case 'exampleVerb':
				// Process the verb invocation
				return false;
			default:
				// Unknown management verb
				assert(false);
				return false;
		}
	}    
} 

class PDFJSPluginDAO extends DAO {
	function &getSubmissionId($reviewId) {

		// return 2;
		
		$result =& $this->retrieve(
			'SELECT r.submission_id FROM review_assignments r LEFT JOIN users u ON (r.reviewer_id = u.user_id) LEFT JOIN review_rounds r2 ON (r.submission_id = r2.submission_id AND r.round = r2.round) WHERE r.review_id = ?', $reviewId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $result->fields['submission_id'];
		}
		$result->Close();
		return $returner;
	}
}


?>

