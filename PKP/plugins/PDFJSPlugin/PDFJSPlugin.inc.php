<?php 
import('classes.plugins.GenericPlugin'); 
import('lib.pkp.classes.db.DAO');

class PDFJSPlugin extends GenericPlugin { 

    function register($category, $path) { 
    	/ print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
        if (parent::register($category, $path)) {    
			HookRegistry::register( 
				'Templates::Reviewer::Submission::inlineText',
				 // 'Templates::Manager::Index::ManagementPages', 
            	array(&$this, 'callback') 
        	); 
        	
            $this->addLocaleData();        	
            // print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";

			if ($this->getEnabled()) {
				// Insert header on every header (callback function checks if it addition is really need)
				HookRegistry::register('TemplateManager::display',array(&$this, 'PDFJSPlugin_TemplateCallback'));
		    	// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
			}            
		   	// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
            return true; 
        } 
    	// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
        return false; 
    } 
    
    function getName() { 
        return 'PDFJSPlugin'; 
    } 
    
    function getDisplayName() { 
        return 'PDF.js plugin'; 
    } 
    
 	function getEnabled() {
		if (Config::getVar('PDFJSPlugin', 'installed')) return true;
		return false;
	}	   
    
    function getViewAnnotate() { 
		return __('plugins.generic.pdfjsplugin.viewAnnotate');
    }
    
	function PDFJSPlugin_TemplateCallback($hookName, $args) {
		//First argument is a TemplateManager object.
		$templateMgr =& $args[0];
    	// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
	
		//Getting some context.
		$journal = &Request::getJournal();
		$journalId = $journal->getJournalId();
		$page = Request::getRequestedPage();
		$op = Request::getRequestedOp();
		$currentJournal = $templateMgr->get_template_vars('currentJournal');

    	// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
    	
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
			if (($articleFile->getFileType() == 'application/pdf') && (strpos($articleFile->getFileName(),'-RV') > 1)) {
				$aFile =& $file_manager->getFile($articleFile->getFileId(), $revision);
				$filePath = $file_manager->filesDir .  $file_manager->fileStageToPath($aFile->getFileStage()) . '/' . $aFile->getFileName(); 

				$path_elements = array_filter(explode('/', Request::getRequestPath()));	
				$journal = array_shift(array_values($path_elements));				
				$reviewId = array_pop(array_values($path_elements));
				
				// $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].str_replace($_SERVER['PATH_INFO'],"", $_SERVER['REQUEST_URI'])."/".$journal."/reviewer/downloadFile/".$reviewId.'/'.$articleFile->_data['submissionId'].'/'.$articleFile->_data['fileId'].'/'.$articleFile->_data['revision'];
				$new_path = Request::url(null, 'reviewer', 'downloadFile', array($reviewId, $articleFile->getSubmissionId(), $articleFile->getFileId(), $articleFile->getRevision()));
				
				// work it into here
				list($discard,$pluginDir) = explode("/plugins/", dirname(__FILE__));
				
				$pluginUrl= Request::getBaseUrl()."/plugins/".$pluginDir;
		
				// the output: a link to the PDF.js HTML viewer
				$output .= '<br/>';		
				$output .= '<a href="'.$pluginUrl.'/pdfjs/web/viewer.html?file='.urlencode($new_path).'" target="_new">'.$this->getViewAnnotate().'</a>';
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

