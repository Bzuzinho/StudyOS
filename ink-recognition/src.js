import * as ort from 'onnxruntime-web';
import { InferenceEngine, preprocessStrokes, isStrokeMeaningful, loadVocab } from 'ink-on/core';

ort.env.wasm.wasmPaths = '/vendor/onnx/';
ort.env.wasm.numThreads = 1;
let engine;
let vocab;
let boot;
async function setup() {
  if (!boot) boot = (async () => {
    vocab = await loadVocab('/vendor/ink-models/vocab.json');
    engine = new InferenceEngine({
      encoderUrl: '/vendor/ink-models/encoder_int8.onnx',
      decoderUrl: '/vendor/ink-models/decoder_int8.onnx',
      executionProvider: 'wasm',
      beamWidth: 3
    });
    await engine.init();
  })().catch(error => {boot = undefined;throw error;});
  return boot;
}
export async function recognizeMath(strokes, width=900, height=400) {
  await setup();
  const ink = strokes.filter(s => s.color !== '#ffffff').map(s => ({
    lineWidth: 3, points: s.points.map(p => ({x:p.x*width,y:p.y*height}))
  }));
  if (!isStrokeMeaningful(ink)) throw new Error('A escrita é demasiado curta para reconhecimento.');
  const result = await engine.recognize(preprocessStrokes(ink), vocab, 'expression');
  return result.latex;
}
