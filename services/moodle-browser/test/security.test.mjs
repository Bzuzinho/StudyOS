import test from 'node:test';
import assert from 'node:assert/strict';
import { authorized, allowedUrl, validateLaunch, parseInput } from '../security.mjs';

test('the private broker requires the exact bearer secret', () => {
  const secret = 's'.repeat(64);
  assert.equal(authorized(`Bearer ${secret}`,secret),true);
  for (const value of [undefined,'',secret,`Bearer ${'x'.repeat(64)}`]) assert.equal(authorized(value,secret),false);
  assert.equal(authorized('Bearer short','short'),false);
});
test('navigation is restricted to institutional and Microsoft HTTPS sites', () => {
  const base='https://ead.ulo.pt/2026-27';
  for(const url of [base,'https://login.microsoftonline.com/common','https://aadcdn.msauth.net/file']) assert.equal(allowedUrl(url,base,true),true);
  for(const url of ['http://ead.ulo.pt/','https://localhost/','https://127.0.0.1/','https://ead.ulo.pt.evil.test/',
    'https://user:secret@ead.ulo.pt/','https://ead.ulo.pt:1234/','file:///etc/passwd','moodlemobile://token=secret',
    'https://cdn.jsdelivr.net/']) assert.equal(allowedUrl(url,base,true),false);
  assert.equal(allowedUrl('https://cdn.jsdelivr.net/asset',base,false),true);
});
test('a browser can only be launched for this Moodle and a fresh-shaped challenge', () => {
  const base='https://ead.ulo.pt/2026-27';
  const launch=`${base}/admin/tool/mobile/launch.php?service=moodle_mobile_app&passport=${'p'.repeat(64)}&confirmed=1`;
  assert.equal(validateLaunch(launch,base),launch);
  for(const value of [launch.replace('ead.ulo.pt','evil.test'),launch.replace('confirmed=1','confirmed=0'),
    launch.replace('moodle_mobile_app','other'),launch.replace('launch.php','index.php'),launch.replace('p'.repeat(64),'short')]) assert.throws(()=>validateLaunch(value,base));
});
test('input cannot execute scripts, navigate an address bar or use arbitrary CDP commands', () => {
  assert.deepEqual(parseInput({type:'key',key:'Tab'}),{type:'key',key:'Tab'});
  assert.equal(parseInput({type:'wheel',deltaY:9999}).deltaY,800);
  assert.deepEqual(parseInput({type:'text',text:'Olá'}),{type:'text',text:'Olá'});
  for(const value of [{type:'key',key:'Control+L'},{type:'evaluate',text:'alert(1)'},{type:'click',x:-1,y:1},
    {type:'click',x:1,y:Infinity},{type:'text',text:'a'.repeat(8193)}]) assert.throws(()=>parseInput(value));
});
