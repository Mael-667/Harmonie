<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ->add('agreeTerms', CheckboxType::class, [
            //     'mapped' => false,
            //     'constraints' => [
            //         new IsTrue(
            //             message: 'You should agree to our terms.',
            //         ),
            //     ],
            // ])
            // 

            // ajouter field file avec constrain image
            ->add('pdp', FileType::class, [
                'mapped' => false,
                'label' => " ",
                'required' => false,
                'constraints' => [
                    new Image(
                            maxSize: "8Mi"
                    ),
                ]
            ])
            ->add('pseudo', TextType::class, [
                "constraints" => [
                    new NotBlank(
                        message: "Votre pseudo ne peut pas être vide"
                    ),
                    new Length(
                        min: 1,
                        minMessage: 'Votre pseudo doit faire au moins {{ limit }} charactères',
                        max: 27,
                        maxMessage: 'Votre pseudo ne peut pas faire plus de {{ limit }} charactères',
                    )
                ]
            ])
            ->add('handle', TextType::class, [
                "constraints" => [
                    new NotBlank(
                        message: "Votre handle ne peut pas être vide"
                    ),
                    new Length(
                        min: 1,
                        minMessage: 'Votre handle doit faire au moins {{ limit }} charactères',
                        max: 27,
                        maxMessage: 'Votre handle ne peut pas faire plus de {{ limit }} charactères',
                    )
                ]
            ])
            ->add("email", EmailType::class, [
                "constraints" => [
                    new NotBlank(
                        message: "Votre email ne peut pas être vide"
                    ),
                    new Email(
                        message: "Votre email n'est pas valide"
                    )
                ]
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez entrer un mot de passe',
                    ),
                    new Length(
                        min: 6,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} charactères',
                        // max length allowed by Symfony for security reasons
                        max: 4096,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
