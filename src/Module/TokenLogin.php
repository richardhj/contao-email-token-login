<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\Module;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class TokenLogin extends AbstractFrontendModuleController
{
    private $tokenChecker;
    private $logoutUrlGenerator;
    private $authenticationUtils;
    private $connection;
    private $tokenGenerator;
    private $router;
    private $translator;
    private $targetPath = '';
    private $twig;

    public function __construct(TokenChecker $tokenChecker, LogoutUrlGenerator $logoutUrlGenerator, AuthenticationUtils $authenticationUtils, Connection $connection, TokenGeneratorInterface $tokenGenerator, RouterInterface $router, TranslatorInterface $translator, TwigEnvironment $twig)
    {
        $this->tokenChecker = $tokenChecker;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
        $this->authenticationUtils = $authenticationUtils;
        $this->connection = $connection;
        $this->tokenGenerator = $tokenGenerator;
        $this->router = $router;
        $this->translator = $translator;
        $this->twig = $twig;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        if ($this->tokenChecker->hasFrontendUser()) {
            /** @var PageModel $pageModel */
            $pageModel = $request->attributes->get('pageModel');
            $redirect = $request->getBaseUrl().'/'.$request->getRequestUri();

            if ($_POST) {
                $this->targetPath = (string) $request->request->get('_target_path');
            } elseif ($model->redirectBack && ($referer = $request->query->get('referer'))) {
                $this->targetPath = $request->getBaseUrl().'/'.base64_decode($referer, true);
            }

            if ($model->redirectBack && $this->targetPath) {
                // Redirect to last page visited
                $redirect = $this->targetPath;
            } elseif ($pageModel->protected) {
                // Redirect home if the page is protected
                $redirect = $request->getBaseUrl();
            }

            $template->logout = true;
            $template->formId = 'tl_logout_'.$model->id;
            $template->slabel = \StringUtil::specialchars($this->translate('MSC.logout'));
            $template->loggedInAs = sprintf($this->translate('MSC.loggedInAs'), FrontendUser::getInstance()->username);
            $template->action = $this->logoutUrlGenerator->getLogoutPath();
            $template->targetPath = \StringUtil::specialchars($redirect);

            if (FrontendUser::getInstance()->lastLogin > 0) {
                $template->lastLogin = sprintf(
                    $this->translate('MSC.lastLogin.1'),
                    date($pageModel->datimFormat ?? 'Y-m-d', (int) FrontendUser::getInstance()->lastLogin)
                );
            }

            return new Response($template->parse());
        }

        if (0 !== $request->request->count()) {
            $member = MemberModel::findByUsername($request->request->get('username'));

            if (null === $member) {
                Message::addError($this->translate('MSC.token_login.invalid_login'), 'richardhj_token_login');
            } else {
                // Generate token
                $token = $this->tokenGenerator->generateToken();
                $this->connection->createQueryBuilder()
                    ->insert('tl_member_login_token')
                    ->values([
                        'tstamp' => '?',
                        'expires' => '?',
                        'member' => '?',
                        'token' => '?',
                        'jumpTo' => '?',
                    ])
                    ->setParameter(0, time())
                    ->setParameter(1, strtotime('+2 hours'))
                    ->setParameter(2, $member->id)
                    ->setParameter(3, $token)
                    ->setParameter(4, $request->request->get('_target_path'))
                    ->execute()
                ;

                // Send notification
                $notificationTokens = $this->getNotificationTokens($request, $member, $token);

                /** @var Notification $notification */
                if (null !== $notification = Notification::findByPk($model->nc_notification)) {
                    $notification->send($notificationTokens);

                    $template->doNotShowForm = true;
                    Message::addInfo($this->translate('MSC.token_login.form_success'), 'richardhj_token_login');
                } else {
                    Message::addError($this->translate('MSC.token_login.form_error'), 'richardhj_token_login');
                }
            }

            return new RedirectResponse($request->getUri());
        }

        $template->username = $this->translate('MSC.username');
        $template->action = $request->getRequestUri();
        $template->slabel = $this->translate('MSC.login');
        $template->value = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
        $template->formId = 't_login_'.$model->id;

        $target = $model->getRelated('jumpTo');
        $targetPath = $target instanceof PageModel ? $target->getFrontendUrl() : $request->getRequestUri();

        $template->targetPath = base64_encode($targetPath);

        if (Message::hasMessages('richardhj_token_login')) {
            $template->message = Message::generate('richardhj_token_login');
        }

        return Response::create($template->parse());
    }

    private function translate(string $key): string
    {
        return $this->translator->trans($key, [], 'contao_default');
    }

    private function getNotificationTokens(Request $request, MemberModel $member, string $token): array
    {
        $notificationTokens = [
            'recipient_email' => $member->email,
            'domain' => $request->getHost(),
            'link' => $this->router->generate(
                'richardhj.contao_email_token_login.token_login',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];

        foreach ($member->row() as $field => $value) {
            $notificationTokens['member_'.$field] = $value;
        }

        try {
            $notificationTokens['login_form_html'] = $this->twig->render('@RichardhjContaoEmailTokenLogin/login_form_email.html.twig', [
                'loginBT' => $this->translate('MSC.loginBT'),
                'form_action' => $this->router->generate(
                    'richardhj.contao_email_token_login.token_login',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ]);
        } catch (\Exception $e) {
        }

        return $notificationTokens;
    }
}
