<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Settings;

use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Form\DataTransformer\ResourceToIdentifierTransformer;
use Chamilo\CoreBundle\Form\Type\YesNoType;
use Chamilo\CoreBundle\Repository\Node\CourseRepository;
use Chamilo\CoreBundle\Tool\AbstractTool;
use Chamilo\CoreBundle\Tool\ToolChain;
use Chamilo\CoreBundle\Transformer\ArrayToIdentifierTransformer;
use Sylius\Bundle\SettingsBundle\Schema\AbstractSettingsBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class CourseSettingsSchema extends AbstractSettingsSchema
{
    protected ToolChain $toolChain;

    public function __construct(
        private readonly CourseRepository $courseRepository,
    ) {}

    public function getProcessedToolChain(): array
    {
        $tools = [];

        /** @var AbstractTool $tool */
        foreach ($this->toolChain->getTools() as $tool) {
            $name = $tool->getTitle();
            $tools[$name] = $name;
        }

        return $tools;
    }

    public function setToolChain(ToolChain $tools): void
    {
        $this->toolChain = $tools;
    }

    public function buildSettings(AbstractSettingsBuilder $builder): void
    {
        $tools = $this->getProcessedToolChain();

        $builder
            ->setDefaults(
                [
                    'active_tools_on_create' => $tools,
                    'display_coursecode_in_courselist' => 'false',
                    'display_teacher_in_courselist' => 'true',
                    'student_view_enabled' => 'true',
                    'go_to_course_after_login' => 'false',
                    'show_navigation_menu' => 'false',
                    'enable_tool_introduction' => 'false',
                    'breadcrumbs_course_homepage' => 'course_title',
                    'example_material_course_creation' => 'true',
                    'allow_course_theme' => 'true',
                    'allow_users_to_create_courses' => 'true',
                    'show_courses_descriptions_in_catalog' => 'true',
                    'send_email_to_admin_when_create_course' => 'false',
                    'allow_user_course_subscription_by_course_admin' => 'true',
                    'course_validation' => 'false',
                    'course_validation_terms_and_conditions_url' => '',
                    'course_hide_tools' => [],
                    'scorm_cumulative_session_time' => 'true',
                    'courses_default_creation_visibility' => '2',
                    // COURSE_VISIBILITY_OPEN_PLATFORM
                    'allow_public_certificates' => 'false',
                    'allow_lp_return_link' => 'true',
                    'course_creation_use_template' => null,
                    'hide_scorm_export_link' => 'false',
                    'hide_scorm_copy_link' => 'false',
                    'hide_scorm_pdf_link' => 'true',
                    'course_catalog_published' => 'false',
                    'course_images_in_courses_list' => 'true',
                    'teacher_can_select_course_template' => 'true',
                    'show_toolshortcuts' => '',
                    'lp_show_reduced_report' => 'false',
                    'course_creation_splash_screen' => 'true',
                    'block_registered_users_access_to_open_course_contents' => 'false',
                    'enable_bootstrap_in_documents_html' => 'false',
                    'view_grid_courses' => 'true',
                    'show_simple_session_info' => 'true',
                    'my_courses_show_courses_in_user_language_only' => 'false',
                    'allow_public_course_with_no_terms_conditions' => 'false',
                    'show_all_sessions_on_my_course_page' => 'true',
                    'disabled_edit_session_coaches_course_editing_course' => 'false',
                    'allow_base_course_category' => 'false',
                    'hide_course_sidebar' => 'true',
                    'allow_course_extra_field_in_catalog' => 'false',
                    'multiple_access_url_show_shared_course_marker' => 'false',
                    'course_category_code_to_use_as_model' => 'MY_CATEGORY',
                    'enable_unsubscribe_button_on_my_course_page' => 'false',
                    'course_creation_donate_message_show' => 'false',
                    'course_creation_donate_link' => '<some donate button html>',
                    'courses_list_session_title_link' => '1',
                    'hide_course_rating' => 'false',
                    'course_log_hide_columns' => '',
                    'course_student_info' => '',
                    'course_catalog_settings' => '',
                    'resource_sequence_show_dependency_in_course_intro' => 'false',
                    'course_sequence_valid_only_in_same_session' => 'false',
                    'course_catalog_display_in_home' => 'false',
                    'course_creation_form_set_course_category_mandatory' => 'false',
                    'course_creation_form_hide_course_code' => 'false',
                    'course_about_teacher_name_hide' => 'false',
                    'course_visibility_change_only_admin' => 'false',
                    'catalog_hide_public_link' => 'false',
                    'course_log_default_extra_fields' => '',
                    'show_courses_in_catalogue' => 'false',
                    'courses_catalogue_show_only_category' => '',
                    'course_creation_by_teacher_extra_fields_to_show' => '',
                    'course_creation_form_set_extra_fields_mandatory' => '',
                    'course_configuration_tool_extra_fields_to_show_and_edit' => '',
                    'course_creation_user_course_extra_field_relation_to_prefill' => '',
                    'allow_edit_tool_visibility_in_session' => 'true',
                    'show_course_duration' => 'false',
                    'access_url_specific_files' => 'false',
                ]
            )
            ->setTransformer(
                'active_tools_on_create',
                new ArrayToIdentifierTransformer()
            )
            ->setTransformer(
                'course_hide_tools',
                new ArrayToIdentifierTransformer()
            )
            ->setTransformer(
                'course_creation_use_template',
                new ResourceToIdentifierTransformer($this->courseRepository, 'id')
            )
        ;

        $allowedTypes = [
            'active_tools_on_create' => ['array'],
            'course_hide_tools' => ['array'],
            'display_coursecode_in_courselist' => ['string'],
            'display_teacher_in_courselist' => ['string'],
            'student_view_enabled' => ['string'],
        ];

        $this->setMultipleAllowedTypes($allowedTypes, $builder);
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $tools = $this->getProcessedToolChain();

        $builder
            ->add('active_tools_on_create', ChoiceType::class, [
                'choices' => $tools,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('display_coursecode_in_courselist', YesNoType::class)
            ->add('display_teacher_in_courselist', YesNoType::class)
            ->add('student_view_enabled', YesNoType::class)
            ->add('go_to_course_after_login', YesNoType::class)
            ->add('show_navigation_menu', ChoiceType::class, [
                'choices' => [
                    'No' => 'false',
                    'Icons only' => 'icons',
                    'Text only' => 'text',
                    'Icons text' => 'iconstext',
                ],
            ])
            ->add('enable_tool_introduction', YesNoType::class)
            ->add('breadcrumbs_course_homepage', ChoiceType::class, [
                'choices' => [
                    'Course homepage' => 'course_home',
                    'Course code' => 'course_code',
                    'Course title' => 'course_title',
                    'Session name and course title' => 'session_name_and_course_title',
                ],
            ])
            ->add('example_material_course_creation', YesNoType::class)
            ->add('allow_course_theme', YesNoType::class)
            ->add('allow_users_to_create_courses', YesNoType::class)
            ->add('show_courses_descriptions_in_catalog', YesNoType::class)
            ->add('send_email_to_admin_when_create_course', YesNoType::class)
            ->add('allow_user_course_subscription_by_course_admin', YesNoType::class)
            ->add('course_validation', YesNoType::class)
            ->add('course_validation_terms_and_conditions_url', UrlType::class)
            ->add('course_hide_tools', ChoiceType::class, [
                'choices' => $tools,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('scorm_cumulative_session_time', YesNoType::class)
            ->add('courses_default_creation_visibility', ChoiceType::class, [
                'choices' => [
                    'Public' => '3',
                    'Open' => '2',
                    'Private' => '1',
                    'Closed' => '0',
                ],
            ])
            ->add('allow_public_certificates', YesNoType::class)
            ->add('allow_lp_return_link', YesNoType::class)
            ->add('course_creation_use_template', EntityType::class, [
                'class' => Course::class,
                'placeholder' => 'Choose ...',
                'empty_data' => null,
                'choice_label' => 'title',
                'choice_value' => 'id',
                'required' => false,
            ])
            ->add('hide_scorm_export_link', YesNoType::class)
            ->add('hide_scorm_copy_link', YesNoType::class)
            ->add('hide_scorm_pdf_link', YesNoType::class)
            ->add('course_catalog_published', YesNoType::class)
            ->add('course_images_in_courses_list', YesNoType::class)
            ->add('teacher_can_select_course_template', YesNoType::class)
            ->add('show_toolshortcuts', YesNoType::class)
            ->add('lp_show_reduced_report', YesNoType::class)
            ->add('course_creation_splash_screen', YesNoType::class)
            ->add('block_registered_users_access_to_open_course_contents', YesNoType::class)
            ->add('enable_bootstrap_in_documents_html', YesNoType::class)
            ->add('view_grid_courses', YesNoType::class)
            ->add('show_simple_session_info', YesNoType::class)
            ->add('my_courses_show_courses_in_user_language_only', YesNoType::class)
            ->add('allow_public_course_with_no_terms_conditions', YesNoType::class)
            ->add('show_all_sessions_on_my_course_page', YesNoType::class)
            ->add('disabled_edit_session_coaches_course_editing_course', YesNoType::class)
            ->add('allow_base_course_category', YesNoType::class)
            ->add('hide_course_sidebar', YesNoType::class)
            ->add('allow_course_extra_field_in_catalog', YesNoType::class)
            ->add('multiple_access_url_show_shared_course_marker', YesNoType::class)
            ->add('course_category_code_to_use_as_model', TextType::class)
            ->add('enable_unsubscribe_button_on_my_course_page', YesNoType::class)
            ->add('course_creation_donate_message_show', YesNoType::class)
            ->add('course_creation_donate_link', TextType::class)
            ->add('courses_list_session_title_link', ChoiceType::class, [
                'choices' => [
                    'No link' => '0',
                    'Default' => '1',
                    'Link' => '2',
                    'Session link' => '3',
                ],
            ])
            ->add('hide_course_rating', YesNoType::class)
            ->add('course_log_hide_columns', TextareaType::class, [
                'attr' => ['rows' => 5, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_student_info', TextareaType::class, [
                'attr' => ['rows' => 5, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_catalog_settings', TextareaType::class, [
                'attr' => ['rows' => 10, 'style' => 'font-family: monospace;'],
            ])
            ->add('resource_sequence_show_dependency_in_course_intro', YesNoType::class)
            ->add('course_sequence_valid_only_in_same_session', YesNoType::class)
            ->add('course_catalog_display_in_home', YesNoType::class)
            ->add('course_creation_form_set_course_category_mandatory', YesNoType::class)
            ->add('course_creation_form_hide_course_code', YesNoType::class)
            ->add('course_about_teacher_name_hide', YesNoType::class)
            ->add('course_visibility_change_only_admin', YesNoType::class)
            ->add('catalog_hide_public_link', YesNoType::class)
            ->add('course_log_default_extra_fields', TextareaType::class, [
                'attr' => ['rows' => 5, 'style' => 'font-family: monospace;'],
            ])
            ->add('show_courses_in_catalogue', YesNoType::class)
            ->add('courses_catalogue_show_only_category', TextareaType::class, [
                'attr' => ['rows' => 3, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_creation_by_teacher_extra_fields_to_show', TextareaType::class, [
                'attr' => ['rows' => 3, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_creation_form_set_extra_fields_mandatory', TextareaType::class, [
                'attr' => ['rows' => 3, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_configuration_tool_extra_fields_to_show_and_edit', TextareaType::class, [
                'attr' => ['rows' => 3, 'style' => 'font-family: monospace;'],
            ])
            ->add('course_creation_user_course_extra_field_relation_to_prefill', TextareaType::class, [
                'attr' => ['rows' => 5, 'style' => 'font-family: monospace;'],
            ])
            ->add('allow_edit_tool_visibility_in_session', YesNoType::class)
            ->add('show_course_duration', YesNoType::class)
            ->add('access_url_specific_files', YesNoType::class)
        ;

        $this->updateFormFieldsFromSettingsInfo($builder);
    }
}
