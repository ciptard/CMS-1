<?php

namespace FrontModule;

/**
* Home page
*/
class HomepagePresenter extends FrontPresenter
{
	private $postFacade;

	private $optionFacade;

    public function __construct(\Flame\Models\Posts\PostFacade $postFacade, \Flame\Models\Options\OptionFacade $optionFacade)
    {
        $this->postFacade = $postFacade;
	    $this->optionFacade = $optionFacade;
    }
	
	public function actionDefault()
	{
		if(!count($this->postFacade->getLastPublishPosts())){
			$this->flashMessage('No posts');
		}	

	}

	public function createComponentPostsControl()
	{
		$postControl = new \Flame\Components\PostsControl($this->postFacade->getLastPublishPosts());

		$itemsPerPage = $this->optionFacade->getOptionValue('items_per_page');
		if($itemsPerPage) $postControl->setCountOfItemsPerPage($itemsPerPage);

		return $postControl;
	}
}

?>