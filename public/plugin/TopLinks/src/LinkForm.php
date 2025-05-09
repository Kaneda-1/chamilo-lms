<?php

/* For license terms, see /license.txt */

namespace Chamilo\PluginBundle\TopLinks\Form;

use Chamilo\PluginBundle\TopLinks\Entity\TopLink;
use FormValidator;
use Image;
use Security;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class LinkForm.
 *
 * @package Chamilo\PluginBundle\TopLinks\Form
 */
class LinkForm extends FormValidator
{
    /**
     * @var TopLink
     */
    private $link;

    /**
     * LinkForm constructor.
     */
    public function __construct(TopLink $link = null)
    {
        $this->link = $link;

        $actionParams = [
            'action' => 'add',
            'sec_token' => Security::get_existing_token(),
        ];

        if ($this->link) {
            $actionParams['action'] = 'edit';
            $actionParams['link'] = $this->link->getId();
        }

        $action = api_get_self().'?'.http_build_query($actionParams);

        parent::__construct('frm_link', 'post', $action, '');
    }

    public function validate(): bool
    {
        return parent::validate() && Security::check_token('get');
    }

    public function exportValues($elementList = null)
    {
        Security::clear_token();

        return parent::exportValues($elementList);
    }

    public function createElements()
    {
        global $htmlHeadXtra;

        $htmlHeadXtra[] = api_get_css_asset('cropper/dist/cropper.min.css');
        $htmlHeadXtra[] = api_get_asset('cropper/dist/cropper.min.js');

        $this->addText('title', get_lang('LinkName'));
        $this->addUrl('url', 'URL');
        $this->addRule('url', get_lang('GiveURL'), 'url');
        $this->addSelect(
            'target',
            [
                get_lang('LinkTarget'),
                get_lang('AddTargetOfLinkOnHomepage'),
            ],
            [
                '_blank' => get_lang('LinkOpenBlank'),
                '_self' => get_lang('LinkOpenSelf'),
            ]
        );
        $this->addFile(
            'picture',
            [
                $this->link ? get_lang('UpdateImage') : get_lang('AddImage'),
                get_lang('OnlyImagesAllowed'),
            ],
            [
                'id' => 'picture',
                'class' => 'picture-form',
                'crop_image' => true,
                'crop_ratio' => '1 / 1',
                'accept' => 'image/*',
            ]
        );
        $allowedPictureTypes = api_get_supported_image_extensions(false);
        $this->addRule(
            'picture',
            get_lang('OnlyImagesAllowed').' ('.implode(', ', $allowedPictureTypes).')',
            'filetype',
            $allowedPictureTypes
        );
        $this->addButtonSave(get_lang('SaveLink'), 'submitLink');
    }

    public function returnForm()
    {
        $defaults = [];

        if ($this->link) {
            $defaults['title'] = $this->link->getTitle();
            $defaults['url'] = $this->link->getUrl();
            $defaults['target'] = $this->link->getTarget();
        }

        $this->setDefaults($defaults);

        return parent::returnForm(); // TODO: Change the autogenerated stub
    }

    public function setLink(TopLink $link): LinkForm
    {
        $this->link = $link;

        return $this;
    }

    public function saveImage(): ?string
    {
        $pictureCropResult = $this->exportValue('picture_crop_result');

        if (empty($pictureCropResult)) {
            return null;
        }

        $extension = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        $newFilename = md5($this->link->getId()).".$extension";
        $directoryName = api_get_path(SYS_UPLOAD_PATH).'plugins/TopLinks';

        $fs = new Filesystem();
        $fs->mkdir($directoryName, api_get_permissions_for_new_directories());

        $image = new Image($_FILES['picture']['tmp_name']);
        $image->crop($pictureCropResult);
        $image->resize(ICON_SIZE_BIG);
        $image->send_image("$directoryName/$newFilename");

        return $newFilename;
    }
}
