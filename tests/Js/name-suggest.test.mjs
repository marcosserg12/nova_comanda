// Run with: npm run test:js
import assert from 'node:assert/strict';
import { capitalize, normalize, prepare, suggestNames } from '../../resources/js/name-suggest.js';

const list = prepare([
    'Bianca Souza', 'Zé da Cocada', // already used in orders (come first)
    'Maria', 'José', 'Ana', 'João', 'Maria Clara', 'Mariana', 'Marina', 'Clara', 'Cláudia', 'Bianca', 'Joana', 'Joaquim',
]);
const surnames = prepare(['Silva', 'Santos', 'Souza', 'Costa', 'Cláudio', 'Clemente']);
const suggest = (value, limit) => suggestNames(list, surnames, value, limit);

assert.equal(normalize('  João   da SILVA '), 'joao da silva');

assert.deepEqual(suggest(''), []);
assert.deepEqual(suggest('   '), []);

// Used names first, then common names, accents ignored.
assert.deepEqual(suggest('bi'), ['Bianca Souza', 'Bianca']);
assert.deepEqual(suggest('joa'), ['João', 'Joana', 'Joaquim']);
assert.deepEqual(suggest('jose'), [], 'the typed name itself is not suggested');

// Up to 6.
assert.equal(suggestNames(prepare(Array.from({ length: 20 }, (_, i) => `Ana ${i}`)), [], 'an').length, 6);

// A trailing space finishes the word: only compound names (surnames wait for the first letter).
assert.deepEqual(suggest('Maria '), ['Maria Clara']);
assert.deepEqual(suggest('Mari'), ['Maria', 'Maria Clara', 'Mariana', 'Marina']);

// The second word is completed with surnames (never with other first names).
assert.deepEqual(suggest('Maria Cl'), ['Maria Clara', 'Maria Cláudio', 'Maria Clemente']);
assert.deepEqual(suggest('Bianca S'), ['Bianca Souza', 'Bianca Silva', 'Bianca Santos']);
assert.deepEqual(suggest('bianca so'), ['Bianca Souza'], 'no duplicate of the used name');
assert.deepEqual(suggest('Zé da C'), ['Zé da Cocada', 'Zé da Costa', 'Zé da Cláudio', 'Zé da Clemente']);

// What was typed in lower case gets capitals in the suggestion.
assert.deepEqual(suggest('zuleide sa'), ['Zuleide Santos']);
assert.deepEqual(suggest('ana maria da si'), ['Ana Maria da Silva']);
assert.equal(capitalize('maria da silva'), 'Maria da Silva');
assert.equal(capitalize('ângela'), 'Ângela');

console.log('name suggestions: OK');
