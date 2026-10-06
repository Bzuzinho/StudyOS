FROM mcr.microsoft.com/playwright:v1.63.0-noble
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts --no-audit --no-fund
COPY . .
ENV NODE_ENV=production
ENV BROWSER_HEADLESS=false
USER pwuser
CMD ["xvfb-run", "--auto-servernum", "node", "server.mjs"]
