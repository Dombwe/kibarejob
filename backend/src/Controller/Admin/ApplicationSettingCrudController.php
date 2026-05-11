<?php

namespace App\Controller\Admin;

use App\Entity\ApplicationSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ApplicationSettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ApplicationSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Parametre serveur')
            ->setEntityLabelInPlural('Parametres serveur')
            ->setDefaultSort(['active' => 'DESC', 'createdAt' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('activeEnvironment', 'Environnement actif')
            ->setChoices([
                'Local / developpement' => ApplicationSetting::ENVIRONMENT_LOCAL,
                'En ligne / production' => ApplicationSetting::ENVIRONMENT_ONLINE,
            ])
            ->setHelp('Choisissez quelle adresse sera exposee a l application mobile via /api/app-config.');
        yield UrlField::new('localBaseUrl', 'Adresse locale')
            ->setHelp('Exemple : https://127.0.0.1:8000 ou http://192.168.11.105:8000.');
        yield UrlField::new('onlineBaseUrl', 'Adresse en ligne')
            ->setHelp('Exemple : https://api.kibarejob.com ou http://198.168.195.226.')
            ->hideOnIndex();
        yield TextField::new('activeBaseUrl', 'Adresse utilisee')
            ->onlyOnIndex();
        yield BooleanField::new('active', 'Active');
        yield TextareaField::new('description', 'Note')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Cree le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifie le')->hideOnForm()->hideOnIndex();
    }
}
