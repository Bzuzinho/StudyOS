# Reconhecimento matemático local (sem API paga)

Bundle do motor Apache-2.0 [ink-on v0.1.0](https://github.com/kimseungdae/ink-on) e ONNX Runtime Web.
O Dockerfile compila o bundle e descarrega os modelos ONNX fixos da release v0.1.0.
O navegador executa o reconhecimento localmente; as imagens e os traços manuscritos não são enviados a serviços externos.
O resultado é uma proposta de transcrição LaTeX, **não** uma classificação e pode conter erros.
Após confirmar a transcrição, apenas os tipos de exercícios suportados pelo corretor determinístico podem ser classificados automaticamente.
Modelos e bibliotecas são carregados da própria aplicação e ocupam cerca de 8 MB na primeira utilização. Rever também os termos de licença dos pesos distribuídos pela release do projeto upstream antes de utilização fora do contexto atual.
