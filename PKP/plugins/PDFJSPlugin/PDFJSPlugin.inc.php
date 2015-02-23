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

		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$submissionId = $reviewAssignment->getSubmissionId();

		# the directory


        $file_manager = new ArticleFileManager($submissionId); // 6); 
       	$dir = $file_manager->filesDir;       

		$DAO = new AuthorSubmissionDAO();
        $files = $DAO->getAuthorSubmission($submissionId); // 6); 
		 
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$articleFiles =& $articleFileDao->getArticleFilesByArticle($submissionId); // 6);

        $PKPReview = new PKPReviewAssignment();
        $PKPReview->setSubmissionId($submissionId);
 
		foreach ($articleFiles as $articleFile) {
			if (($articleFile->_data['filetype'] == 'application/pdf') && (strpos($articleFile->_data['fileName'],'-RV') > 1)) {
				$aFile =& $file_manager->getFile($articleFile->_data['fileId'], $revision);
				$filePath = $file_manager->filesDir .  $file_manager->fileStageToPath($aFile->getFileStage()) . '/' . $aFile->getFileName(); 

				$path_elements = array_filter(explode('/', $_SERVER['PATH_INFO']));	
				$journal = array_shift(array_values($path_elements));				
				$reviewId = array_pop(array_values($path_elements));
				
				// $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].str_replace($_SERVER['PATH_INFO'],"", $_SERVER['REQUEST_URI'])."/".$journal."/reviewer/downloadFile/".$reviewId.'/'.$articleFile->_data['submissionId'].'/'.$articleFile->_data['fileId'].'/'.$articleFile->_data['revision'];
				$new_path = Request::url(null, 'reviewer', 'downloadFile', array($reviewId, $articleFile->_data['submissionId'], $articleFile->_data['fileId'], $articleFile->_data['revision']));
				
				// work it into here
				$plugin_dir = str_replace($_SERVER['DOCUMENT_ROOT'],"",dirname(__FILE__));
	
				// why is this not kicking out info?
				$output .= '<br/>';		
				$output .= '<a href="'.$plugin_dir.'/pdfjs/web/viewer.html?file='.urlencode($new_path).'" target="_new">Open to view and annotate</a>';
				$output .= '<br/>';		
			}
		}


       
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

?>

