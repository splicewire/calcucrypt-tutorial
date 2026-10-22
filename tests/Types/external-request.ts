import type { ExternalRequestData } from '../../resources/js/generated/App/Data';
declare const row: ExternalRequestData;
const source: string = row.source;
// @ts-expect-error The declared external source identity is a string.
const invalid: number = row.source;
void source;
void invalid;
