// The stamped registration is in effect (frame-foundation ruling 03, slice 4): imported by path, it narrows frame's
// ResourceKey to the generated map's keys. A missing registration fails the import; a registration that is present
// but ignored leaves ResourceKey as string, and the directive below then fails as unused.
import '../../resources/js/frame-resource-map';
import type { ResourceKey } from '@schemastud/frame';

const known: ResourceKey = 'beam-ux-entry';
// @ts-expect-error a key the generated map does not declare
const unknown: ResourceKey = 'no-such-resource';

void [known, unknown];
