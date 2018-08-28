<?php

/**
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) 2018-2018 Richard Henkenjohann
 *
 * @package   richardhj/contao-email-token-login
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2018-2018 Richard Henkenjohann
 * @license   https://github.com/richardhj/contao-email-token-login/blob/master/LICENSE
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\Module;


use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Translation\TranslatorInterface;

class TokenLogin extends AbstractFrontendModuleController
{
    /**
     * @var TokenChecker
     */
    private $tokenChecker;

    /**
     * @var LogoutUrlGenerator
     */
    private $logoutUrlGenerator;

    /**
     * @var AuthenticationUtils
     */
    private $authenticationUtils;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TokenGeneratorInterface
     */
    private $tokenGenerator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * TokenLogin constructor.
     *
     * @param TokenChecker            $tokenChecker
     * @param LogoutUrlGenerator      $logoutUrlGenerator
     * @param AuthenticationUtils     $authenticationUtils
     * @param Connection              $connection
     * @param TokenGeneratorInterface $tokenGenerator
     * @param RouterInterface         $router
     * @param TranslatorInterface     $translator
     */
    public function __construct(
        TokenChecker $tokenChecker,
        LogoutUrlGenerator $logoutUrlGenerator,
        AuthenticationUtils $authenticationUtils,
        Connection $connection,
        TokenGeneratorInterface $tokenGenerator,
        RouterInterface $router,
        TranslatorInterface $translator
    ) {
        $this->tokenChecker        = $tokenChecker;
        $this->logoutUrlGenerator  = $logoutUrlGenerator;
        $this->authenticationUtils = $authenticationUtils;
        $this->connection          = $connection;
        $this->tokenGenerator      = $tokenGenerator;
        $this->router              = $router;
        $this->translator          = $translator;
    }

    /**
     * Generate the response.
     *
     * @param Template    $template
     * @param ModuleModel $model
     * @param Request     $request
     *
     * @return Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        if ($this->tokenChecker->hasFrontendUser()) {
            /** @var PageModel $objPage */
            global $objPage;

            $redirect = $request->getBaseUrl() . '/' . $request->getRequestUri();

            // Redirect to last page visited
            if ($model->redirectBack && $_SESSION['LAST_PAGE_VISITED']) {
                $redirect = $request->getBaseUrl() . '/' . $_SESSION['LAST_PAGE_VISITED'];
            } // Redirect home if the page is protected
            elseif ($objPage->protected) {
                $redirect = $request->getBaseUrl();
            }

            $template->logout     = true;
            $template->formId     = 'tl_logout_' . $model->id;
            $template->slabel     =
                \StringUtil::specialchars($this->translator->trans('MSC.logout', [], 'contao_default'));
            $template->loggedInAs =
                sprintf(
                    $this->translator->trans('MSC.loggedInAs', [], 'contao_default'),
                    FrontendUser::getInstance()->username
                );
            $template->action     = $this->logoutUrlGenerator->getLogoutPath();
            $template->targetPath = \StringUtil::specialchars($redirect);

            if (FrontendUser::getInstance()->lastLogin > 0) {
                $template->lastLogin = sprintf(
                    $this->translator->trans('MSC.lastLogin.1', [], 'contao_default'),
                    \Date::parse($objPage->datimFormat, FrontendUser::getInstance()->lastLogin)
                );
            }

            return Response::create($template->parse());
        }

        if (0 !== $request->request->count()) {
            $member = MemberModel::findByUsername($request->request->get('username'));
            if (null === $member) {
                $template->hasError = true;
                $template->message  = $this->translator->trans('ERR.invalidLogin', [], 'contao_default');;
            } else {
                try {
                    $objTarget = $model->getRelated('jumpTo');
                    $jumpTo    = ($objTarget instanceof PageModel) ? $objTarget->id : 0;
                } catch (\Exception $e) {
                    $jumpTo = 0;
                }

                // Generate token
                $token = $this->tokenGenerator->generateToken();
                $this->connection->createQueryBuilder()
                    ->insert('tl_member_login_token')
                    ->values(
                        [
                            'tstamp'  => '?',
                            'expires' => '?',
                            'member'  => '?',
                            'token'   => '?',
                            'jumpTo'  => '?',
                        ]
                    )
                    ->setParameter(0, time())
                    ->setParameter(1, strtotime('+2 hours'))
                    ->setParameter(2, $member->id)
                    ->setParameter(3, $token)
                    ->setParameter(4, $jumpTo)
                    ->execute();

                // Send notification
                $notificationTokens = [
                    'recipient_email' => $member->email,
                    'domain'          => $request->getHost(),
                    'link'            => $this->router->generate(
                        'richardhj.contao_email_token_login.token_login',
                        ['token' => $token],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ];

                foreach ($member->row() as $field => $value) {
                    $notificationTokens['member_' . $field] = $value;
                }

                /** @var Notification $notification */
                $notification = Notification::findByPk($model->nc_notification);
                if (null !== $notification) {
                    $notification->send($notificationTokens);

                    $template->doNotShowForm = true;
                    $template->message       =
                        $this->translator->trans('MSC.token_login.form_success', [], 'contao_default');
                } else {
                    $template->hasError = true;
                    $template->message  =
                        $this->translator->trans('MSC.token_login.form_error', [], 'contao_default');
                }
            }
        }

        $template->username = $this->translator->trans('MSC.username', [], 'contao_default');
        $template->action   = $request->getRequestUri();
        $template->slabel   =
            StringUtil::specialchars($this->translator->trans('MSC.login', [], 'contao_default'));
        $template->value    = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
        $template->formId   = 'tl_login_' . $model->id;

        return Response::create($template->parse());
    }
}
