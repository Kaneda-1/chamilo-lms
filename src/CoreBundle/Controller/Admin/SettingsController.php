<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Controller\Admin;

use Chamilo\CoreBundle\Controller\BaseController;
use Chamilo\CoreBundle\Entity\SettingsCurrent;
use Chamilo\CoreBundle\Entity\SettingsValueTemplate;
use Chamilo\CoreBundle\Helpers\AccessUrlHelper;
use Chamilo\CoreBundle\Traits\ControllerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin')]
class SettingsController extends BaseController
{
    use ControllerTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator
    ) {}

    #[Route('/settings', name: 'admin_settings')]
    public function index(): Response
    {
        return $this->redirectToRoute('chamilo_platform_settings', ['namespace' => 'platform']);
    }

    /**
     * Edit configuration with given namespace.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/settings/search_settings', name: 'chamilo_platform_settings_search')]
    public function searchSetting(Request $request): Response
    {
        $manager = $this->getSettingsManager();
        $formList = [];
        $keyword = $request->query->get('keyword');

        $searchForm = $this->getSearchForm();
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $values = $searchForm->getData();
            $keyword = $values['keyword'];
        }

        if (empty($keyword)) {
            throw $this->createNotFoundException();
        }

        $settingsFromKeyword = $manager->getParametersFromKeywordOrderedByCategory($keyword);

        $settings = [];
        if (!empty($settingsFromKeyword)) {
            foreach ($settingsFromKeyword as $category => $parameterList) {
                if (empty($category)) {
                    continue;
                }

                $list = [];
                foreach ($parameterList as $parameter) {
                    $list[] = $parameter->getVariable();
                }
                $settings = $manager->load($category, null);
                $schemaAlias = $manager->convertNameSpaceToService($category);
                $form = $this->getSettingsFormFactory()->create($schemaAlias);

                foreach (array_keys($settings->getParameters()) as $name) {
                    if (!\in_array($name, $list, true)) {
                        $form->remove($name);
                        $settings->remove($name);
                    }
                }
                $form->setData($settings);
                $formList[$category] = $form->createView();
            }
        }

        $schemas = $manager->getSchemas();

        return $this->render(
            '@ChamiloCore/Admin/Settings/search.html.twig',
            [
                'keyword' => $keyword,
                'schemas' => $schemas,
                'settings' => $settings,
                'form_list' => $formList,
                'search_form' => $searchForm,
            ]
        );
    }

    /**
     * Edit configuration with given namespace.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/settings/{namespace}', name: 'chamilo_platform_settings')]
    public function updateSetting(Request $request, AccessUrlHelper $accessUrlHelper, string $namespace): Response
    {
        $manager = $this->getSettingsManager();
        $url = $accessUrlHelper->getCurrent();
        $manager->setUrl($url);
        $schemaAlias = $manager->convertNameSpaceToService($namespace);
        $searchForm = $this->getSearchForm();

        $keyword = '';
        $settingsFromKeyword = null;

        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $values = $searchForm->getData();
            $keyword = $values['keyword'];
            $settingsFromKeyword = $manager->getParametersFromKeyword(
                $schemaAlias,
                $keyword
            );
        }

        $keywordFromGet = $request->query->get('keyword');
        if ($keywordFromGet) {
            $keyword = $keywordFromGet;
            $searchForm->setData([
                'keyword' => $keyword,
            ]);
            $settingsFromKeyword = $manager->getParametersFromKeyword(
                $schemaAlias,
                $keywordFromGet
            );
        }

        $settings = $manager->load($namespace);
        $form = $this->getSettingsFormFactory()->create($schemaAlias);

        if (!empty($keyword)) {
            $params = $settings->getParameters();
            foreach (array_keys($params) as $name) {
                if (!\array_key_exists($name, $settingsFromKeyword)) {
                    $form->remove($name);
                }
            }
        }

        $form->setData($settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageType = 'success';

            try {
                $manager->save($form->getData());
                $message = $this->trans('Settings have been successfully updated');
            } catch (ValidatorException $validatorException) {
                $message = $this->trans($validatorException->getMessage());
                $messageType = 'error';
            }

            $this->addFlash($messageType, $message);

            if (!empty($keyword)) {
                return $this->redirectToRoute('chamilo_platform_settings_search', [
                    'keyword' => $keyword,
                ]);
            }

            return $this->redirectToRoute('chamilo_platform_settings', [
                'namespace' => $namespace,
            ]);
        }

        $schemas = $manager->getSchemas();

        $templateMap = [];
        $settingsRepo = $this->entityManager->getRepository(SettingsCurrent::class);

        $settingsWithTemplate = $settingsRepo->findBy(['url' => $url]);

        foreach ($settingsWithTemplate as $s) {
            if ($s->getValueTemplate()) {
                $templateMap[$s->getVariable()] = $s->getValueTemplate()->getId();
            }
        }

        return $this->render(
            '@ChamiloCore/Admin/Settings/default.html.twig',
            [
                'schemas' => $schemas,
                'settings' => $settings,
                'form' => $form->createView(),
                'keyword' => $keyword,
                'search_form' => $searchForm,
                'template_map' => $templateMap,
            ]
        );
    }

    /**
     * Sync settings from classes with the database.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/settings_sync', name: 'sync_settings')]
    public function syncSettings(AccessUrlHelper $accessUrlHelper): Response
    {
        $manager = $this->getSettingsManager();
        $url = $accessUrlHelper->getCurrent();
        $manager->setUrl($url);
        $manager->installSchemas($url);

        return new Response('Updated');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/settings/template/{id}', name: 'chamilo_platform_settings_template')]
    public function getTemplateExample(int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(SettingsValueTemplate::class);
        $template = $repo->find($id);

        if (!$template) {
            return $this->json([
                'error' => $this->translator->trans('Template not found.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'variable' => $template->getVariable(),
            'json_example' => $template->getJsonExample(),
            'description' => $template->getDescription(),
        ]);
    }

    /**
     * @return FormInterface
     */
    private function getSearchForm()
    {
        $builder = $this->container->get('form.factory')->createNamedBuilder('search');
        $builder->add('keyword', TextType::class);
        $builder->add('search', SubmitType::class, ['attr' => ['class' => 'btn btn--primary']]);

        return $builder->getForm();
    }
}
