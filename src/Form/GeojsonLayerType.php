<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class GeojsonLayerType extends AbstractType
{
    public function __construct(TranslatorInterface $t)
    {
        $this->t = $t;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $this->t->trans('geojsonLayers.fields.name', [], 'admin'),
                'attr' => ['required' => true]
            ])
            ->add('url', UrlType::class, [
                'label' => $this->t->trans('geojsonLayers.fields.url', [], 'admin'),
                'attr' => ['required' => true]
            ])
            ->add('optionnal', CheckboxType::class, [
                'label' => $this->t->trans('geojsonLayers.fields.optionnal', [], 'admin')
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
          'data_class' => 'App\Document\GeojsonLayer',
      ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gogo_elementbundle_geojsonLayer';
    }
}
