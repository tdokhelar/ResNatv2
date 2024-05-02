<?php

namespace App\Controller;

use App\Document\Coordinates;
use App\Document\ElementFile;
use App\Document\ElementImage;
use App\Document\ElementStatus;
use App\Document\OpenHours;
use App\Document\PostalAddress;
use App\Services\ElementActionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class InteroperabilityAPIController extends GoGoController
{

    public function getAuthorizedProjects(DocumentManager $dm)
    {
        $authorizedProjects = $dm->get('AuthorizedProject')->findAll();
        $response = new Response(json_encode(array_map(
            fn($value) => [
                    'url' => $value->getUrl(),
                    'isActivated' => $value->getIsActivated()
            ],
            $authorizedProjects
        )));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    public function editElementFromAuthorizedGogocartoProject(
        $id,
        Request $request,
        DocumentManager $dm,
        ElementActionService $elementActionService,
        TranslatorInterface $t
    ) {
        
        if ($request->getMethod() !== 'PUT') {
            return $this->json([], JsonResponse::HTTP_BAD_REQUEST);
        }

        $content = $request->getContent();
        $json = json_decode($content, true);
        extract($json); // => $gogoFeature, $externalOperator, $apiKey
        
        $authorizedProjects = $dm->get('AuthorizedProject')->findAll();
        $hasPermission = false;
        forEach($authorizedProjects as $authorizedProject) {
            if ( $authorizedProject->getUrl() === $externalOperator
                && $authorizedProject->getApiKey() === $apiKey
                && $authorizedProject->getIsActivated() === true
            ) {
                $hasPermission = true;
            }
        }
        if (!$hasPermission) {
            return $this->json([], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = $gogoFeature['data'];
        $mappedCategories = $gogoFeature['mappedCategories'];
        
        $element = $dm->get('Element')->find($id);
        if (!$element || !$element->isFullyEditable()) {
            return $this->json([], JsonResponse::HTTP_NOT_FOUND);
        }
        $originalElement = $element->clone(); 
        
        $dataToUpdate = [];
        forEach($data as $key => $value) {
            $oldValue = $element->getProperty($key);
            if ($oldValue !== $value) {
                $dataToUpdate[$key]['oldValue'] = $oldValue;
                $dataToUpdate[$key]['newValue'] = $value;
            }
        }

        $categoriesToUpdate = [];
        forEach($mappedCategories as $key => $value) {
            $oldValue = in_array($key, $element->getOptionIds());
            if ($oldValue !== $value) {
                $categoriesToUpdate[$key]['oldValue'] = $oldValue;
                $categoriesToUpdate[$key]['newValue'] = $value;
            }
        }

        if (count($dataToUpdate) === 0 && count($categoriesToUpdate) === 0) {
            return $this->json(['message' => 'nothing_to_do'], JsonResponse::HTTP_OK);
        }

        try {  
            
            $editableCoreFields = [
                'name' => ['class' => false],
                'files' => ['class' => 'ElementFile'],
                'images' => ['class' => 'ElementImage'],
                'latitude' => ['class' => 'Coordinates'],
                'longitude' => ['class' => 'Coordinates'],
                'streetNumber' => ['class' => 'PostalAddress'],
                'streetAddress' => ['class' => 'PostalAddress'],
                'addressLocality' => ['class' => 'PostalAddress'],
                'postalCode' => ['class' => 'PostalAddress'],
                'addressCountry' => ['class' => 'PostalAddress'],
                'customFormatedAddress' => ['class' => 'PostalAddress'],
                'openHours' => ['class' => 'OpenHours'],
            ];

            // Update item in database : data
            forEach($dataToUpdate as $key => $value) {
                
                if (array_key_exists($key, $editableCoreFields)) {
                    
                    switch ($editableCoreFields[$key]['class'])  {

                        case 'Coordinates': 
                            $geo = $element->getGeo();
                            if (!$geo) {
                                $geo = new Coordinates();
                            }
                            $setter = 'set' . ucfirst($key);
                            $geo->$setter($value['newValue']);
                            $element->setGeo($geo);
                        break;

                        case 'PostalAddress': 
                            $address = $element->getAddress();
                            if (!$address) {
                                $address = new PostalAddress();
                            }
                            $setter = 'set' . ucfirst($key);
                            $address->$setter($value['newValue']);
                            $element->setAddress($address);
                        break;

                        case 'OpenHours': 
                            $newOpenHours = new OpenHours(json_decode($value['newValue'], true));
                            $element->setOpenHours($newOpenHours);
                        break;
                        
                        case 'ElementImage':
                            $element->resetImages();
                            $images = $value['newValue'];
                            foreach ($images as $imageUrl) {
                                if (is_string($imageUrl) && strlen($imageUrl) > 5) {
                                    $elementImage = new ElementImage();
                                    $elementImage->setExternalImageUrl($imageUrl);
                                    $element->addImage($elementImage);
                                }
                            }
                        break;
                        
                        case 'ElementFile':
                            $element->resetFiles();
                            $files = $value['newValue'];
                            foreach ($files as $fileUrl) {
                                if (is_string($fileUrl) && strlen($fileUrl) > 5) {
                                    $elementFile = new ElementFile();
                                    $elementFile->setFileUrl($fileUrl);
                                    $name = explode('/', $fileUrl);
                                    $name = end($name);
                                    $elementFile->setFileName($name);
                                    $element->addFile($elementFile);
                                }
                            }
                        break;
                        
                        default:
                            $setter = 'set' . ucfirst($key);
                            $element->$setter($value['newValue']);
                        break;
                    }

                } else {
                    
                    $element->setCustomProperty($key, $value['newValue']);
                }
            }
            
            // Update item in database : categories
            $newOptionsIds = $element->getOptionIds();
            forEach($categoriesToUpdate as $key => $value) {
                if ($dm->get('Option')->find($key)) {
                    if ($value['newValue'] === true) {
                        array_push($newOptionsIds, $key);
                    } elseif ($value['newValue'] === false) {
                        $newOptionsIds = array_diff($newOptionsIds, [$key]);
                    }
                }
            }
            $element->setOptionIds($newOptionsIds);

            $element->setStatus(ElementStatus::ModifiedFromOtherProject);
            $elementActionService->edit($element, $originalElement, true, null, true, $externalOperator);
            $dm->flush();

            return $this->json([
                'datatoUpdate' => $dataToUpdate,
                'categoriestoUpdate' => $categoriesToUpdate,
            ], JsonResponse::HTTP_OK);

        } catch (\Exception $e) {
            
            return $this->json([
                'message' => $e
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}