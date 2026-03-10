module.exports = () => {
  // Load the generator catalog from the admin JSON source.
  const generators = require('../admin/generators.json');
  
  // Filter out duplicates based on ID
  const seen = new Set();
  return generators.filter(gen => {
    if (seen.has(gen.id)) {
      return false;
    }
    seen.add(gen.id);
    return true;
  });
};

