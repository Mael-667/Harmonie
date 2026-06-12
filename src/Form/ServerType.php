<?php

namespace App\Form;

use App\Entity\Server;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ServerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', FileType::class, [
                'mapped' => false,
                'label' => " ",
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: "8Mi"
                    ),
                ]
            ])
            ->add('name', TextType::class, [
                'label' => "Nom du serveur",
                "constraints" => [
                    new NotBlank(
                        message: "Le nom du serveur ne peut pas être vide"
                    ),
                    new Length(
                        min: 1,
                        minMessage: 'Le nom du serveur doit faire au moins {{ limit }} charactères',
                        max: 27,
                        maxMessage: 'Le nom du serveur ne peut pas faire plus de {{ limit }} charactères',
                    )
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Server::class,
            // Le form est rendu par React (FormPost), pas par Twig : le CSRF est géré
            // manuellement dans AppController::newServer via le token 'app'.
            'csrf_protection' => false,
        ]);
    }
}
