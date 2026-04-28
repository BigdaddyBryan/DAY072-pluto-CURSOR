# Readme

Welcome to the `fonts` directory!

This directory is intended for storing custom fonts that will be used in your project. Feel free to add any font files (.ttf, .otf, etc.) to this directory.

## Usage

To use a custom font in your project, follow these steps:

1. Copy the font file(s) into the `fonts` directory.
2. In your CSS file, use the `@font-face` rule to define the font family and specify the path to the font file(s). For example:

  ```css
  @font-face {
    font-family: 'CustomFont';
    src: url('fonts/CustomFont.ttf');
  }
  ```

3. Apply the custom font to the desired elements in your HTML or CSS using the `font-family` property. For example:

  ```css
  body {
    font-family: 'CustomFont', sans-serif;
  }
  ```

That's it! Your custom font should now be applied to the specified elements in your project.

Feel free to explore and experiment with different fonts to enhance the visual appeal of your project.
