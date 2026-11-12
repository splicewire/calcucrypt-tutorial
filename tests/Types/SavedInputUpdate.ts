import type {ApiSavedInputsUpdateVars} from '../../resources/js/generated/hooks/api-saved-inputs';
const rename: ApiSavedInputsUpdateVars = {params: {id: 'saved-id'}, body: {name: 'Renamed'}};
// @ts-expect-error null is not an omitted business map
const clear: ApiSavedInputsUpdateVars = {params: {id: 'saved-id'}, body: {inputs: null}};
void [rename, clear];
