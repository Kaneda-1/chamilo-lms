<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\CoreBundle\Helpers;

use Chamilo\CoreBundle\Entity\ExtraField;
use Chamilo\CoreBundle\Entity\ExtraFieldValues;
use Chamilo\CoreBundle\Entity\User;
use Chamilo\CoreBundle\Repository\ExtraFieldRepository;
use Chamilo\CoreBundle\Repository\ExtraFieldValuesRepository;
use Chamilo\CoreBundle\Repository\Node\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

readonly class AzureAuthenticatorHelper
{
    public const EXTRA_FIELD_ORGANISATION_EMAIL = 'organisationemail';
    public const EXTRA_FIELD_AZURE_ID = 'azure_id';
    public const EXTRA_FIELD_AZURE_UID = 'azure_uid';

    public const QUERY_USER_FIELDS = [
        'givenName',
        'surname',
        'mail',
        'userPrincipalName',
        'businessPhones',
        'mobilePhone',
        'accountEnabled',
        'mailNickname',
        'id',
    ];
    public const QUERY_GROUP_FIELDS = [
        'id',
        'displayName',
        'description',
    ];

    public const QUERY_GROUP_MEMBERS_FIELDS = [
        'mail',
        'mailNickname',
        'id',
    ];

    public function __construct(
        private ExtraFieldValuesRepository $extraFieldValuesRepo,
        private ExtraFieldRepository $extraFieldRepo,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private AccessUrlHelper $accessUrlHelper,
    ) {}

    /**
     * @throws NonUniqueResultException
     */
    public function registerUser(array $azureUserInfo): User
    {
        if (empty($azureUserInfo)) {
            throw new UnauthorizedHttpException('User info not found.');
        }

        [
            $firstNme,
            $lastName,
            $username,
            $email,
            $phone,
            $authSource,
            $active,
            $extra,
        ] = $this->formatUserData($azureUserInfo);

        $userId = $this->getUserIdByVerificationOrder($azureUserInfo);

        if (empty($userId)) {
            $user = (new User())
                ->setCreatorId($this->userRepository->getRootUser()->getId())
            ;
        } else {
            $user = $this->userRepository->find($userId);
        }

        $user
            ->setFirstname($firstNme)
            ->setLastname($lastName)
            ->setEmail($email)
            ->setUsername($username)
            ->setPlainPassword('azure')
            ->setStatus(STUDENT)
            ->addAuthSourceByAuthentication(
                $authSource,
                $this->accessUrlHelper->getCurrent()
            )
            ->setPhone($phone)
            ->setActive($active)
            ->setRoleFromStatus(STUDENT)
        ;

        $this->userRepository->updateUser($user);

        $url = $this->accessUrlHelper->getCurrent();
        $url->addUser($user);

        $this->entityManager->flush();

        $this->extraFieldValuesRepo->updateItemData(
            $this->getOrganizationEmailField(),
            $user,
            $extra['extra_'.self::EXTRA_FIELD_ORGANISATION_EMAIL]
        );

        $this->extraFieldValuesRepo->updateItemData(
            $this->getAzureIdField(),
            $user,
            $extra['extra_'.self::EXTRA_FIELD_AZURE_ID]
        );

        $this->extraFieldValuesRepo->updateItemData(
            $this->getAzureUidField(),
            $user,
            $extra['extra_'.self::EXTRA_FIELD_AZURE_UID]
        );

        return $user;
    }

    private function getOrganizationEmailField(): ExtraField
    {
        return $this->extraFieldRepo->findByVariable(
            ExtraField::USER_FIELD_TYPE,
            self::EXTRA_FIELD_ORGANISATION_EMAIL
        );
    }

    private function getAzureIdField(): ExtraField
    {
        return $this->extraFieldRepo->findByVariable(
            ExtraField::USER_FIELD_TYPE,
            self::EXTRA_FIELD_AZURE_ID
        );
    }

    private function getAzureUidField(): ExtraField
    {
        return $this->extraFieldRepo->findByVariable(
            ExtraField::USER_FIELD_TYPE,
            self::EXTRA_FIELD_AZURE_UID
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getUserIdByVerificationOrder(array $azureUserData): ?int
    {
        $selectedOrder = $this->getExistingUserVerificationOrder();

        $organisationEmailField = $this->getOrganizationEmailField();
        $azureIdField = $this->getAzureIdField();
        $azureUidField = $this->getAzureUidField();

        /** @var array<int, ExtraFieldValues> $positionsAndFields */
        $positionsAndFields = [
            1 => $this->extraFieldValuesRepo->findByVariableAndValue($organisationEmailField, $azureUserData['mail']),
            2 => $this->extraFieldValuesRepo->findByVariableAndValue($azureIdField, $azureUserData['mailNickname']),
            3 => $this->extraFieldValuesRepo->findByVariableAndValue($azureUidField, $azureUserData['id']),
        ];

        foreach ($selectedOrder as $position) {
            if (!empty($positionsAndFields[$position])) {
                return $positionsAndFields[$position]->getItemId();
            }
        }

        return null;
    }

    public function getExistingUserVerificationOrder(): array
    {
        return [1, 2, 3];
    }

    private function formatUserData(array $azureUserData): array
    {
        $phone = null;

        if (isset($azureUserData['telephoneNumber'])) {
            $phone = $azureUserData['telephoneNumber'];
        } elseif (isset($azureUserData['businessPhones'][0])) {
            $phone = $azureUserData['businessPhones'][0];
        } elseif (isset($azureUserData['mobilePhone'])) {
            $phone = $azureUserData['mobilePhone'];
        }

        // If the option is set to create users, create it
        $firstNme = $azureUserData['givenName'];
        $lastName = $azureUserData['surname'];
        $email = $azureUserData['mail'];
        $username = $azureUserData['userPrincipalName'];
        $authSource = 'azure';
        $active = ($azureUserData['accountEnabled'] ? 1 : 0);
        $extra = [
            'extra_'.self::EXTRA_FIELD_ORGANISATION_EMAIL => $azureUserData['mail'],
            'extra_'.self::EXTRA_FIELD_AZURE_ID => $azureUserData['mailNickname'],
            'extra_'.self::EXTRA_FIELD_AZURE_UID => $azureUserData['id'],
        ];

        return [
            $firstNme,
            $lastName,
            $username,
            $email,
            $phone,
            $authSource,
            $active,
            $extra,
        ];
    }

    /**
     * The keys are the user roles, as defined for the group_ip parameter in the authentication.yaml file for Azure.
     *
     * @return array<string, callable>
     */
    public function getUpdateActionByRole(): array
    {
        return [
            'admin' => function (User $user): void {
                $user
                    ->setStatus(COURSEMANAGER)
                    ->addUserAsAdmin()
                    ->setRoleFromStatus(COURSEMANAGER)
                ;
            },
            'session_admin' => function (User $user): void {
                $user
                    ->setStatus(SESSIONADMIN)
                    ->removeUserAsAdmin()
                    ->setRoleFromStatus(SESSIONADMIN)
                ;
            },
            'teacher' => function (User $user): void {
                $user
                    ->setStatus(COURSEMANAGER)
                    ->removeUserAsAdmin()
                    ->setRoleFromStatus(COURSEMANAGER)
                ;
            },
        ];
    }
}
