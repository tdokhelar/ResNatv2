import OsmQueryBuilder from './OsmQueryBuilder.vue'
import Vue from '../../vendor/vue-custom'

document.addEventListener('DOMContentLoaded', function() {
    if ($('#element-import').length > 0) {
        new Vue({
            el: "#element-import",
            data: {
                sourceType: undefined,
                url: undefined,
                osmQueriesJson: undefined,
                formName: "",
            },
            computed: {
                osmQueryInputValue() {
                    if (!this.osmQueriesJson) return ""
                    let result = {}
                    result.address = this.osmQueriesJson.address
                    result.bounds = this.osmQueriesJson.bounds
                    result.overPassCustomQuery = this.osmQueriesJson.overPassCustomQuery
                    result.queries = []
                    for(let query of this.osmQueriesJson.queries) {
                        result.queries.push(query.filter(condition => condition.key))
                    }
                    return JSON.stringify(result)
                }
            },
            components: { OsmQueryBuilder },
            mounted() {
                for(let key in importObject) this[key] = importObject[key]
                this.osmQueriesJson = JSON.parse(this.osmQueriesJson)
                this.sourceType = sourceType;
                this.formName = formName
                $(`#sonata-ba-field-container-${formName}_file`).appendTo('.file-container')
            }
        })
        // idsToIgnore Field
        const form = document.querySelector('.sonata-ba-form > form');
        const idsToIgnoreHiddenInput = form.querySelector('input[type=hidden][id*="idsToIgnore"]')
        const stringifiedIdsToIgnoreinput = idsToIgnoreHiddenInput.cloneNode(true); 
        stringifiedIdsToIgnoreinput.name = 'stringifiedIdsToIgnore';
        stringifiedIdsToIgnoreinput.id = 'stringifiedIdsToIgnore';
        form.append(stringifiedIdsToIgnoreinput);
        var observer = new MutationObserver(() => {
            stringifiedIdsToIgnoreinput.value = idsToIgnoreHiddenInput.value;
        });
        observer.observe(idsToIgnoreHiddenInput,  { attributes: true });
        $('button[type=submit]').click((e) => {
          $('input[type=hidden][name*="[idsToIgnore]["]').remove();
        });
        $('#btn-clearall-idstoignore').click((e)=> {
            e.preventDefault();
            $('input[type=hidden][id*="idsToIgnore"]').val(null).trigger('change');
        })
    }
})
