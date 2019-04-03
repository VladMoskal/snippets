<?php

namespace BKontor\AdminBundle\Controller;

use BKontor\BaseBundle\Entity\AdminRight;
use Symfony\Component\HttpFoundation\Request;
use BKontor\AdminBundle\Form\Type\EditProviderSettingType;
use BKontor\BaseBundle\Entity\KYCProvider;
use BKontor\BaseBundle\Entity\AnnexConfig;


class KYCProviderController extends AbstractAdminController
{
    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function overviewAction()
    {

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_VIEW)) {
            $this->addFlashMessage(sprintf('You have no rights to overview kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_home'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $paymentProcessorsTableService = $this->get('datatable.kyc_providers');

        return $this->render('BKontorAdminBundle:KYCProviders:overview.html.twig', array(
            'dataTable' => $paymentProcessorsTableService,
        ));

    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deactivateAction($id)
    {

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to deactivate kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $kycProvider = $em->getRepository('BKontorBaseBundle:KYCProvider')->find($id);
        /** @var $licenseService \BKontor\BaseBundle\Service\LicenseService */
        $licenseService = $this->get('bkontor.license');

        if (!$licenseService->hasOption($kycProvider->getName())) {
            $this->addFlashMessage(sprintf('You have no license for "%s" kyc providers.', $kycProvider->getName()), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }
        $kycProvider->setActivated(false);

        $em->persist($kycProvider);

        $annexGroup = $em->getRepository('BKontorBaseBundle:AnnexGroup')->findOneBy(["name" => "kyc_1_idents"]);
        $providerAnnexes = $em->getRepository('BKontorBaseBundle:KYCProviderAnnex')->findBy(["provider_name" => $kycProvider->getName()]);

        foreach ($providerAnnexes as $annexName) {
            $annex = $em->getRepository('BKontorBaseBundle:AnnexConfig')->find($annexName->getAnnexIdent());

            if ($annex && ($annex->getAnnexGroup()->getName() == $annexGroup->getName())) {
                if ($annex->containsKycProvider($kycProvider)) {
                    $annex->removeKycProvider($kycProvider);
                }
            }

            if (!$annex) {
                continue;
            }

            $em->persist($annex);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));

    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function activateAction($id)
    {

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to activate kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $kycProvider = $em->getRepository('BKontorBaseBundle:KYCProvider')->find($id);

        /** @var $licenseService \BKontor\BaseBundle\Service\LicenseService */
        $licenseService = $this->get('bkontor.license');

        if (!$licenseService->hasOption($kycProvider->getName())) {
            $this->addFlashMessage(sprintf('You have no license for "%s" kyc providers.', $kycProvider->getName()), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }
        $kycProvider->setActivated(true);
        $em->persist($kycProvider);

        $annexGroup = $em->getRepository('BKontorBaseBundle:AnnexGroup')->findOneBy(["name" => "kyc_1_idents"]);
        $providerAnnexes = $em->getRepository('BKontorBaseBundle:KYCProviderAnnex')->findBy(["provider_name" => $kycProvider->getName()]);

        foreach ($providerAnnexes as $annexName) {
            $annex = $em->getRepository('BKontorBaseBundle:AnnexConfig')->find($annexName->getAnnexIdent());
            if ($annex && !$annex->containsKycProvider($kycProvider) && $annexName->isRequired()) {
                $annex->addKycProvider($kycProvider)->setAnnexGroup($annexGroup);
            } elseif (!$annex && $annexName->isRequired()) {
                $annex = new AnnexConfig();
                $annex->setAllowChange(true);
                $annex->setAnnexGroup($annexGroup);
                $annex->setAnnexIdent($annexName->getAnnexIdent());
                $annex->setRequired(true);
                $annex->setProtected(false);
                $annex->setLabel($annexName->getDescription());
                $annex->setType('text');
                $annex->addKycProvider($kycProvider);
            }

            if (!$annex) {
                continue;
            }

            $em->persist($annex);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction($id)
    {
        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to config kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $KYCProvider = $em->getRepository('BKontorBaseBundle:KYCProvider')->find($id);
        /** @var $licenseService \BKontor\BaseBundle\Service\LicenseService */
        $licenseService = $this->get('bkontor.license');

        if (!$licenseService->hasOption($KYCProvider->getName())) {
            $this->addFlashMessage(sprintf('You have no license for "%s" kyc providers.', $KYCProvider->getName()), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        $providerAnnexes = $em->getRepository('BKontorBaseBundle:KYCProviderAnnex')->findBy(["provider_name" => $KYCProvider->getName()]);

        $createdAnnexes = [];
        foreach ($providerAnnexes as $providerAnnex) {
            $annexConfig = $em->getRepository('BKontorBaseBundle:AnnexConfig')->find($providerAnnex->getAnnexIdent());
            if ($annexConfig) {
                $createdAnnexes[] = $providerAnnex->getAnnexIdent();
            }
        }

        return $this->render('BKontorAdminBundle:KYCProviders:provider.html.twig', array(
            'provider' => $KYCProvider,
            'annexes' => $providerAnnexes,
            'createdAnnexes' => $createdAnnexes
        ));

    }

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function editTestSettingAction(Request $request, $id)
    {
        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to config kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $kycProviderSetting = $em->getRepository('BKontorBaseBundle:KYCProvidersSetting')->find($id);

        $kp = $kycProviderSetting->getProvider();

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_TEST)) {
            $this->addFlashMessage(sprintf('You have no rights to edit KYC provider test settings.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_provider_edit'));
        }

        $defaultData = array(
            'value' => $kycProviderSetting->getTestValue(),
            'description' => $kycProviderSetting->getDescription()
        );

        $settingName = $kycProviderSetting->getName();

        $form = $this->createForm(new EditProviderSettingType($settingName), $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $kycProviderSetting->setTestValue($data['value']);
            $kycProviderSetting->setDescription($data['description']);

            $em->persist($kycProviderSetting);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_kyc_provider_edit', array(
                'id' => $kp->getId(),
            )));
        }

        return $this->render('BKontorAdminBundle:KYCProviders:editSetting.html.twig', array(
            'form' => $form->createView(),
            'providerName' => $kp->getName(),
            'providerEnv' => KYCProvider::TEST,
            'providerSettingName' => $kycProviderSetting->getName()
        ));
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function editProductionSettingAction(Request $request, $id)
    {
        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to config kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $kycProviderSetting = $em->getRepository('BKontorBaseBundle:KYCProvidersSetting')->find($id);

        $kp = $kycProviderSetting->getProvider();

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_PRODUCTION)) {
            $this->addFlashMessage(sprintf('You have no rights to edit KYC provider production settings.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_provider_edit'));
        }

        $defaultData = array(
            'value' => $kycProviderSetting->getProductionValue(),
            'description' => $kycProviderSetting->getDescription()
        );

        $settingName = $kycProviderSetting->getName();

        $form = $this->createForm(new EditProviderSettingType($settingName), $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $kycProviderSetting->setProductionValue($data['value']);
            $kycProviderSetting->setDescription($data['description']);

            $em->persist($kycProviderSetting);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_kyc_provider_edit', array(
                'id' => $kp->getId(),
            )));
        }

        return $this->render('BKontorAdminBundle:KYCProviders:editSetting.html.twig', array(
            'form' => $form->createView(),
            'providerName' => $kp->getName(),
            'providerEnv' => KYCProvider::PRODUCTION,
            'providerSettingName' => $kycProviderSetting->getName()
        ));
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function switchEnvironmentAction($id)
    {

        if (!$this->adminHasRight(AdminRight::KYC_PROVIDERS_CONFIG)) {
            $this->addFlashMessage(sprintf('You have no rights to config kyc providers.'), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->get('doctrine.orm.entity_manager');

        $KYCProvider = $em->getRepository('BKontorBaseBundle:KYCProvider')->find($id);

        /** @var $licenseService \BKontor\BaseBundle\Service\LicenseService */
        $licenseService = $this->get('bkontor.license');

        if (!$licenseService->hasOption($KYCProvider->getName())) {
            $this->addFlashMessage(sprintf('You have no license for "%s" kyc providers.', $KYCProvider->getName()), 'error');

            return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));
        }

        $environment = $KYCProvider->getState();

        switch ($environment) {
            case KYCProvider::TEST:
                $KYCProvider->setState(KYCProvider::PRODUCTION);
                break;
            case KYCProvider::PRODUCTION:
                $KYCProvider->setState(KYCProvider::TEST);
                break;
        }

        $em->persist($KYCProvider);
        $em->flush();

        return $this->redirect($this->generateUrl('admin_kyc_providers_overview'));

    }
}