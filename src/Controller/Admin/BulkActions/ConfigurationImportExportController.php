<?php

namespace App\Controller\Admin\BulkActions;

use App\DataFixtures\MongoDB\LoadConfiguration;
use App\Document\Category;
use App\Document\Configuration\ConfigurationExport;
use App\Document\Option;
use App\Document\IconImage;
use Datetime;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use App\Services\ConfigurationService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;

class ConfigurationImportExportController extends Controller
{
    public function __construct(DocumentManager $dm, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->form = null;
        $this->t = $t; 
    }
    
    public function renderConfigImport ()
    {
        return $this->render('config-import/config_import.html.twig', [
            'form' => $this->form->createView(),
            'config' => $this->dm->get('Configuration')->findConfiguration(),
        ]);
    }

    private function trans($key, $params = [])
    {
        return $this->t->trans($key, $params, 'admin');
    }
        
    public function configImport(
        DocumentManager $dm, 
        LoadConfiguration $loadConfiguration,
        Request $request        
    ) {
        
        $this->form = $this->createFormBuilder()
            ->add('cbConfiguration', CheckboxType::class, [
                'label' => $this->trans('config_import_export.choices.cbConfiguration'),
                'required' => false,
                'row_attr' => ['class' => 'checkbox checkbox-wrapper']
            ])
            ->add('cbTaxonomies', CheckboxType::class, [
                'label' => $this->trans('config_import_export.choices.cbTaxonomies'),
                'required' => false,
                'row_attr' => ['class' => 'checkbox checkbox-wrapper checkbox-wrapper-root']
            ])
            ->add('cbKeepExistingTaxonomies', CheckboxType::class, [
                'label' => $this->trans('config_import_export.choices.cbKeepExistingTaxonomies'),
                'required' => false,
                'row_attr' => ['class' => 'checkbox checkbox-wrapper checkbox-wrapper-sublevel']
            ])
            ->add('url', UrlType::class, [
                'label' => $this->trans('config_import_export.texts.url'),
                'required' => false,
                'row_attr' => ['class' => 'form-group'],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->trans('config_import_export.texts.url_placeholder')
                ]
            ])
            ->add('file', FileType::class, [
                'label' => $this->trans('config_import_export.texts.uploadFile'),
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'application/json',
                            'text/plain'
                        ],
                        'mimeTypesMessage' => '({{ type }}) ' . $this->trans('config_import_export.errors.jsonFile'),
                    ])
                ],
                'row_attr' => ['class' => 'file-upload file-upload-wrapper'],
                'error_bubbling' => true
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->trans('config_import_export.actions.importConfirmation'),
                'attr' => ['class' => 'btn btn-success']
            ])
            ->getForm();
            
        $this->form->handleRequest($request);
        if ( $this->form->isSubmitted() ) {
            
            $validationErrors = $this->form->getErrors();
            if ( count($validationErrors) > 0) {
                forEach( $validationErrors as $validationError ) {
                    $this->addFlash('error', $validationError->getMessage());
                }
                // Error : Form validator
                return $this->renderConfigImport();
            }

            if ( $this->form->isValid() ) {
            
                $data = $this->form->getData();
                extract($data);
                $file = $this->form->get('file')->getData();

                $source = null;
                $importStartDatetime = new Datetime();
                
                if ( ! $cbConfiguration &&  ! $cbTaxonomies ) {
                    // Error : No Choice made
                    $this->addFlash('error', $this->trans('config_import_export.errors.noChoiceMade'));
                    return $this->renderConfigImport();
                    
                } else {
                    
                    if ($url) {

                        $jsonFile = @file_get_contents($url);
                        
                        if ( ! $jsonFile || ! json_decode($jsonFile) ) {
                            $url = str_replace('//api/', '/api/', $url . '/api/full-configuration.json');
                            $jsonFile = @file_get_contents($url);
                        }
                        
                        if ( ! $jsonFile || ! json_decode($jsonFile) ) {
                            $this->addFlash('error', $this->trans('config_import_export.errors.invalidUrl'));
                            return $this->renderConfigImport();
                        } else {
                            $source = $url;
                        }
                        
                    } else if ($file) {
                        $source = $file->getPathname();
                    }
                    
                    if ( ! $source ) {
                        // Error : No source
                        $this->addFlash('error', $this->trans('config_import_export.errors.noSource'));
                        return $this->renderConfigImport();
                        
                    } else {
                        
                        $fileContent = json_decode(file_get_contents($source));
                        
                        if ( ! $fileContent ) {
                            // Error : No content
                            $this->addFlash('error', $this->trans('config_import_export.errors.jsonFormat') . ' (1)');
                            return $this->renderConfigImport();
                            
                        } else {

                            if ($cbConfiguration) {
                                $configToCopy = null;
                                $itemsExportConfigToCopy = null;
                                if ( property_exists($fileContent, 'configuration') ) {
                                    $configToCopy = $fileContent->configuration;
                                    if ( property_exists($fileContent, 'itemsExportConfig') ) {
                                        $itemsExportConfigToCopy = $fileContent->itemsExportConfig;
                                    }
                                } else {
                                    // Error : Invalid Json content
                                    $this->addFlash('error', $this->trans('config_import_export.errors.jsonFormat') . ' (2)');
                                    return $this->renderConfigImport();
                                }
                            }
                            
                            if ($cbTaxonomies) {
                                $taxoToCopy = null;
                                if ( property_exists($fileContent, 'taxonomies') ) {
                                    $taxoToCopy = $fileContent->taxonomies;
                                } else {
                                    // Error : Invalid Json content
                                    $this->addFlash('error', $this->trans('config_import_export.errors.jsonFormat') . ' (3)');
                                    return $this->renderConfigImport();
                                }
                            }
                            
                            if ($cbConfiguration) {
                                $loadConfiguration->load($dm, null, $configToCopy, 'copy');
                                if ($itemsExportConfigToCopy) {
                                    $this->loadItemsExportConfig($dm, $itemsExportConfigToCopy);                                    
                                }
                            }
                            if ($cbTaxonomies) {
                                $this->loadTaxonomies($dm, $taxoToCopy);
                                
                                if (! $cbKeepExistingTaxonomies) {
                                    $oldCategories = $dm->get('Category')->findOlderCategories($importStartDatetime);
                                    foreach($oldCategories as $oldCategory) {
                                        $dm->remove($oldCategory);
                                    }
                                    $oldOptions = $dm->get('Option')->findOlderOptions($importStartDatetime);
                                    foreach($oldOptions as $oldOption) {
                                        $dm->remove($oldOption);
                                    }
                                }
                            }
                            $dm->flush();
                            $this->addFlash('success', $this->trans('config_import_export.texts.importOk'));
                        }
                    }
                }
            }
        }
        
        return $this->renderConfigImport();
    }
    
    public function loadTaxonomies(DocumentManager $dm, $rootCategories)
    {
        foreach ($rootCategories as $rootCategory) {
            $this->loadCategory($dm, $rootCategory);
        }
    }
    
    public function loadCategory(DocumentManager $dm, $taxoCategory, $parent = null)
    {
        $category = new Category();
        $i=0;
        foreach ($taxoCategory as $key => $value) {
            $i++;
            if ($value || is_bool($value)) {
                switch ($key) {
                    case 'id': break;
                    case 'parent': break;
                    case 'options':
                        foreach($value as $taxoOption) {
                            $this->loadOption($dm, $taxoOption, $category, $i);
                        }
                    break;
                    default:
                        $key = 'set'.ucfirst($key);
                        if ( method_exists($category, $key) ) {
                            $category->$key($value);
                        }
                }
            }
        }
        $dm->persist($category);
        if ($parent) {
            $category->setParent($parent);
        }
    }
    
    public function loadOption(DocumentManager $dm, $taxoOption, $parent, $i)
    {
        $option = new Option();
        foreach ($taxoOption as $key => $value) {
            if ($value || is_bool($value)) {
                switch ($key) {
                    case 'id': break;
                    case 'parent': break;
                    case 'iconFile':
                        $icon = new IconImage();
                        $icon->setExternalImageUrl($value->externalImageUrl ?: $value->fileUrl);
                        $icon->setFileName($value->fileName);
                        $option->setIconFile($icon);
                        break;
                    case 'subcategories':
                        foreach($value as $taxoCategory) {
                            $this->loadCategory($dm, $taxoCategory, $option);
                        }
                        break;
                    default:
                        $key = 'set'.ucfirst($key);
                        if ( method_exists($option, $key) ) {
                            $option->$key($value);
                        }
                }
            }
        }
        $dm->persist($option);
        $option->setParent($parent);
    }
    
    public function loadItemsExportConfig(DocumentManager $dm, $ItemsExportConfig)
    {
        foreach ($ItemsExportConfig as $iec) {
            $configurationExport = new ConfigurationExport();
            foreach ($iec as $key => $value) {
                if ($value || is_bool($value)) {
                    switch ($key) {
                        case 'id': break;
                        default:
                            $key = 'set'.ucfirst($key);
                            $configurationExport->$key($value);
                    }
                }
            }
            $dm->persist($configurationExport);
        }
    }
    
    public function configExport(
        ConfigurationService $configurationService,
        SerializerInterface $serializer
    ) {
        $config = $this->dm->get('Configuration')->findConfiguration();
        $sanitizedAppName = preg_replace('/[^\w\._]+/', '_', $config->getAppName());
        $now = new Datetime();
        $formatedDateTime = $now->format('Y-m-d-H-i-s');
        $filename = 'gogocarto-' . $sanitizedAppName . '-' . $formatedDateTime . '.json';

        $response = new Response(json_encode(
            $configurationService->getFullConfig($serializer),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
