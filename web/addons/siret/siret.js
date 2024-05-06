const gogoAddon_siret = {

init: () => {

    console.log('gogoAddon_siret:', 'loaded');

    const searchSiretToggler = document.querySelector('#search-siret-toggler');
    const searchSiretWrapper = document.querySelector('#search-siret-wrapper');
    const nameInput = document.querySelector('#input-name');
    const addressInputPostalCode = document.querySelector('#input-postalCode');
    const addressInputLocality = document.querySelector('#input-addressLocality');
    const siretInput = document.querySelector('#input-siret');
    const searchSiretButton = document.querySelector('#search-siret-button');
    const searchSiretResultTemplate = document.querySelector('#search-siret-results-list li');
    const searchSiretMessage = document.querySelector('#search-siret-message');
    const searchSiretAddressCheckbox = document.querySelector('#search-siret-address-checkbox');

    if (searchSiretToggler) {
    searchSiretToggler.addEventListener('click', (e) => {
            e.preventDefault();
            searchSiretWrapper.classList.toggle('open');
            setTimeout(() => {
            searchSiretToggler.querySelector('span.open').classList.toggle('active');
            searchSiretToggler.querySelector('span.close').classList.toggle('active');
        }, 300);
    })
    }

    if (nameInput && siretInput && searchSiretButton && searchSiretResultTemplate) {

    searchSiretButton.addEventListener('click', (e) => {

        e.preventDefault();
        searchSiretMessage.textContent = '';
        document.querySelectorAll('.search-siret-result:not(.template)').forEach((resultNode) => {
            resultNode.remove();
        })

        if (nameInput.value === '') {
            searchSiretMessage.textContent = 'Veuillez saisir le titre de la fiche avant de lancer la recherche.';
        return;
        }

        if (nameInput.value.length < 3) {
            searchSiretMessage.textContent = 'Veuillez saisir un titre de fiche avec au moins 3 caractères avant de lancer la recherche.';
        return;
        }

        const apiUrl = 'https://recherche-entreprises.api.gouv.fr/search'
        const headers = { 'Accept': 'application/json' };
        const queryParams = new URLSearchParams({ 
            q: nameInput.value,
        });
        if (searchSiretAddressCheckbox.checked && addressInputPostalCode.value) {
            queryParams.append('code_postal', addressInputPostalCode.value);
        }
        const url = `${apiUrl}?${queryParams}`;

        console.log('gogoAddon_siret:', 'Search', searchSiretAddressCheckbox.checked, url);

        fetch(url, {
            method: 'GET',
            headers: headers
        })
        .then(response => {
            if (!response.ok) {
                searchSiretMessage.textContent = 'Erreur lors de la recherche du SIRET.';
                console.error('gogoAddon_siret: Erreur lors de la recherche du SIRET.', 'Network error');
            }
            return response.json();
        })
        .then(data => {
            if (data.results.length === 0) {
                searchSiretMessage.textContent = 'Aucun résultat.';
                return;
            }
            data.results.forEach((result, index) => {
                const matchingEtablissements = result.matching_etablissements;
                matchingEtablissements.forEach((matchingEtablissement, index2) => {
                    const resultNode = searchSiretResultTemplate.cloneNode(true);
                    resultNode.id = resultNode.id + '-' + index + '-' + index2;
                    resultNode.classList.remove('template');
                    resultNode.querySelector('.search-siret-result__siret').textContent = matchingEtablissement.siret;
                    resultNode.querySelector('.search-siret-result__name').textContent = result.nom_complet;
                    resultNode.querySelector('.search-siret-result__address').textContent = matchingEtablissement.adresse;
                    resultNode.style.display = 'flex';
                    searchSiretResultTemplate.parentNode.append(resultNode);
                    resultNode.querySelector('.search-siret-result__select-button')
                    .addEventListener('click', (e) => {
                        e.preventDefault();
                        document.querySelectorAll('.search-siret-result').forEach((resultNode) => {
                        resultNode.classList.remove('selected');
                        })
                        e.currentTarget.closest('.search-siret-result').classList.add('selected');
                        siretInput.value = matchingEtablissement.siret;
                        siretInput.focus();
                    });
                });
            })
        })
        .catch(error => {
            searchSiretMessage.textContent = 'Erreur lors de la recherche du SIRET.';
            console.error('gogoAddon_siret: Erreur lors de la recherche du SIRET.', error);
        });

    })
    }
}
}

document.addEventListener('DOMContentLoaded', gogoAddon_siret.init);
