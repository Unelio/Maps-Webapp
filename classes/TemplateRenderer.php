<?php

class TemplateRenderer
{
  public function render(string $templatePath, array $replacements = []): string
  {
    if (!is_readable($templatePath)) {
      return '';
    }

    $template = (string)file_get_contents($templatePath);

    foreach ($replacements as $key => $value) {
      $template = str_replace('{{' . strtoupper((string)$key) . '}}', (string)$value, $template);
    }

    return $template;
  }
}